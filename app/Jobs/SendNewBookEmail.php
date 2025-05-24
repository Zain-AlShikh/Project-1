<?php

namespace App\Jobs;

use App\Mail\NewBookAdded;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendNewBookEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;
    protected $bookData;

    public function __construct($email, $bookData)
    {
        $this->email = $email;
        $this->bookData = $bookData;
    }

    public function handle()
    {
        Mail::to($this->email)->send(new NewBookAdded($this->bookData));
    }
}
