<?php

namespace App\Security;

enum EmailOtpSendResult
{
    case Sent;
    /** Le compte n'a pas d'adresse e-mail valide (AUTH-2FA-05). */
    case NoEmail;
    /** 1 envoi par minute, 5 par heure (AUTH-2FA-08). */
    case Throttled;
}
