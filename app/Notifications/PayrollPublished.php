<?php

namespace App\Notifications;

use App\Models\Payroll;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PayrollPublished extends Notification
{
    use Queueable;

    public function __construct(private readonly Payroll $payroll)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $month = date('M Y', strtotime($this->payroll->year_month . '-01'));
        $amount = number_format((float) $this->payroll->net_amount);
        $currency = $this->payroll->currency ?? 'JPY';

        return [
            'title' => 'Payroll',
            'message' => "Your payroll for {$month} has been published. Net Amount: {$currency} {$amount}",
            'link' => route('app.payroll.index', ['month' => $this->payroll->year_month]),
            'type' => 'payroll',
        ];
    }
}
