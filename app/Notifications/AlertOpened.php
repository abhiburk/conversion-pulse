<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertOpened extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Alert $alert) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->alert->campaign->name ?? $this->alert->site->name;

        return (new MailMessage)
            ->subject(__(':type: :subject', ['type' => $this->alert->type->label(), 'subject' => $subject]))
            ->line(__('Conversion Pulse detected a problem with :subject on :site.', [
                'subject' => $subject,
                'site' => $this->alert->site->domain,
            ]))
            ->line($this->alert->summary())
            ->action(__('View alerts'), route('alerts.index'))
            ->line(__('The alert resolves itself once tracking is back to normal.'));
    }
}
