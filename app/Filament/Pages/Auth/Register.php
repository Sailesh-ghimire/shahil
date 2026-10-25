<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Register as BaseRegister;

/**
 * Self sign-up. Pending invitations for the email are accepted only after the address is verified
 * (see AcceptPendingInvitations), so nobody can claim a workspace seat by registering someone else's email.
 */
class Register extends BaseRegister {}
