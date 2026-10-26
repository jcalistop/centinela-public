<?php

namespace App\Listeners;

use App\Support\Mail\MailAddressSafety;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class RejectCrlfInMailAddresses
{
    public function handle(MessageSending $event): void
    {
        $this->assertEmailIsSafe($event->message);
    }

    private function assertEmailIsSafe(Email $message): void
    {
        foreach (['getFrom', 'getReplyTo', 'getTo', 'getCc', 'getBcc'] as $getter) {
            foreach ($message->{$getter}() as $address) {
                $this->assertAddressIsSafe($address);
            }
        }
    }

    private function assertAddressIsSafe(Address $address): void
    {
        MailAddressSafety::rejectIfContainsCrlf($address->getAddress());

        $name = $address->getName();
        if ($name !== null) {
            MailAddressSafety::rejectIfContainsCrlf($name);
        }
    }
}
