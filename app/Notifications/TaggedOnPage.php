<?php
namespace App\Notifications;

use App\Models\PageTag;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaggedOnPage extends Notification
{
    use Queueable;

    public function __construct(public PageTag $tag) {}

    public function via(object $notifiable): array
    {
        return $notifiable->notify_email && ! $notifiable->is_demo ? ['database', 'mail'] : ['database'];
    }

    private function url(): string
    {
        return route('editions.show', ['edition' => $this->tag->edition->slug, 'page' => $this->tag->page_number]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'tag', 'tag_id' => $this->tag->id,
            'message' => ($this->tag->tagger?->name ?? 'Someone').' tagged you on page '.$this->tag->page_number.' of '.$this->tag->edition->title,
            'url' => $this->url(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('You were tagged in The Red Star')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->toArray($notifiable)['message'].'.')
            ->when($this->tag->note, fn ($m) => $m->line('“'.$this->tag->note.'”'))
            ->action('See the page', $this->url())
            ->line('You can remove the tag from the page, or turn off these emails in your profile.');
    }
}
