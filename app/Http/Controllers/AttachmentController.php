<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function chunkUpload(Request $request)
    {
        $request->validate([
            'chunkIndex' => 'required|integer',
            'totalChunks' => 'required|integer',
            'fileName' => 'required|string',
            'identifier' => 'required|string',
            'file' => 'required|file',
            'attachable_type' => 'nullable|string',
            'attachable_id' => 'nullable|integer',
        ]);

        $chunkIndex = $request->input('chunkIndex');
        $totalChunks = $request->input('totalChunks');
        $fileName = $request->input('fileName');
        $identifier = $request->input('identifier');
        $file = $request->file('file');

        $tempDir = storage_path('app/chunks/' . $identifier);
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $file->move($tempDir, $chunkIndex);

        $uploadedChunks = count(glob($tempDir . '/*'));
        if ($uploadedChunks === (int) $totalChunks) {
            $finalPath = 'attachments/' . date('Y/m') . '/' . uniqid() . '_' . $fileName;
            $fullFinalPath = storage_path('app/public/' . $finalPath);

            $dir = dirname($fullFinalPath);
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            $out = fopen($fullFinalPath, 'wb');
            for ($i = 0; $i < $totalChunks; $i++) {
                $inPath = $tempDir . '/' . $i;
                if (file_exists($inPath)) {
                    $in = fopen($inPath, 'rb');
                    stream_copy_to_stream($in, $out);
                    fclose($in);
                    unlink($inPath);
                }
            }
            fclose($out);
            @rmdir($tempDir);

            $mimeType = mime_content_type($fullFinalPath);
            if (!$mimeType) {
                $mimeType = 'application/octet-stream';
            }

            $attachment = Attachment::create([
                'attachable_type' => $request->input('attachable_type'),
                'attachable_id' => $request->input('attachable_id'),
                'disk' => 'public',
                'path' => $finalPath,
                'original_name' => $fileName,
                'mime_type' => $mimeType,
                'size_bytes' => filesize($fullFinalPath),
                'uploaded_by' => $request->user()->id,
            ]);

            return response()->json([
                'status' => 'completed',
                'attachment' => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'is_image' => str_starts_with((string) $attachment->mime_type, 'image/'),
                    'is_pdf' => ($attachment->mime_type ?? '') === 'application/pdf',
                    'preview_url' => route('chat.attachments.preview', $attachment),
                    'download_url' => route('chat.attachments.download', $attachment),
                ]
            ]);
        }

        return response()->json([
            'status' => 'chunk_received',
            'chunkIndex' => $chunkIndex
        ]);
    }
    public function destroy(Request $request, Attachment $attachment)
    {
        $user = $request->user();

        // 所有者チェック（attachableのuser_idと比較）
        $attachable = $attachment->attachable;
        $ownerId = $attachable?->user_id;

        if (!$ownerId || (int) $ownerId !== (int) $user->id) {
            abort(403);
        }

        // 物理削除
        Storage::disk($attachment->disk)->delete($attachment->path);

        $attachment->delete();

        return back()->with('success', 'Attachment deleted.');
    }
}
