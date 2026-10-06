<?php

namespace App\Notifications;

use App\Models\EventRequest;
use Illuminate\Notifications\Messages\MailMessage;

class NewEventRequest extends NeboNotification
{
    private bool $mailOnly = false;

    public function __construct(public EventRequest $request) {}

    /** For the configured copy addresses (not users). */
    public function viaMail(): static
    {
        $this->mailOnly = true;

        return $this;
    }

    public function via(object $notifiable): array
    {
        return $this->mailOnly ? ['mail'] : parent::via($notifiable);
    }

    public function title(): string
    {
        return 'New production request';
    }

    public function body(): string
    {
        return "{$this->request->reference}: {$this->request->event_name} on {$this->request->event_date->format('j M Y')}, from ".($this->request->company ?: $this->request->contact_person).'.';
    }

    public function url(): ?string
    {
        return route('app.requests.show', $this->request);
    }

    public function level(): string
    {
        return 'warning';
    }

    public function icon(): string
    {
        return 'inbox';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('New production request '.$this->request->reference)
            ->line($this->body())->action('Open the request', $this->url());
    }
}
