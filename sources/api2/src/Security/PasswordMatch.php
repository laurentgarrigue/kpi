<?php

namespace App\Security;

/** Résultat de {@see PasswordVerifier::verify()} : quel hash a validé le mot de passe. */
enum PasswordMatch
{
    case None;
    case Bcrypt;
    /** Ancien hash MD5 (AUTH-MD-01) : le contrôleur le remplace par un bcrypt. */
    case LegacyMd5;
}
