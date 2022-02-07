<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MentionMail extends Mailable
{
    use Queueable, SerializesModels;
    public $comment_id;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($commentId)
    {
        $this->comment_id = $commentId;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.mentionMail')->with('id', $this->comment_id);
    }
}
