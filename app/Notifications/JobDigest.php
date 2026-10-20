<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class JobDigest extends Notification
{
    public function __construct(public array $jobs) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject(count($this->jobs).' new job matches · JobFinder')->greeting('Your next opportunity awaits.');
        foreach ($this->jobs as $job) {
            $mail->line($job['title'].' at '.$job['company_name'].' · '.($job['location'] ?: 'Location unspecified'))->line($job['url']);
        }

        return $mail->action('Explore your matches', route('jobs.index'))->line('Manage alerts: '.route('preferences.edit'))->line('Unsubscribe: '.URL::signedRoute('alerts.unsubscribe', ['user' => $notifiable->id]));
    }
}
