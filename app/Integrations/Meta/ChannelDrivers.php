<?php

namespace App\Integrations\Meta;

use App\Enums\Channel;
use App\Integrations\Meta\Contracts\ChannelDriver;
use App\Integrations\Meta\Drivers\InstagramDriver;
use App\Integrations\Meta\Drivers\MessengerDriver;
use App\Integrations\Meta\Drivers\WhatsAppDriver;

class ChannelDrivers
{
    public function for(Channel $channel): ChannelDriver
    {
        return match ($channel) {
            Channel::WhatsApp => app(WhatsAppDriver::class),
            Channel::Facebook => app(MessengerDriver::class),
            Channel::Instagram => app(InstagramDriver::class),
        };
    }
}
