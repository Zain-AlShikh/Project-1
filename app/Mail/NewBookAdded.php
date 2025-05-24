<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewBookAdded extends Mailable
{
use Queueable, SerializesModels;

    public $title, $author, $isbn, $publish_year, $pages_count, $description, $language;

    public function __construct($bookData)
    {
        $this->title        = $bookData['title'];
        $this->author       = $bookData['author'];
        $this->isbn         = $bookData['isbn'];
        $this->publish_year = $bookData['publish_year'];
        $this->pages_count  = $bookData['pages_count'];
        $this->description  = $bookData['description'];
        $this->language     = $bookData['language'];
    }

    public function build()
    {
        return $this->subject("New Book Added: {$this->title}")
                    ->view('emails.new_book');
    }
}
