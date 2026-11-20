<?php

namespace App\Security;

/** Issue de la vérification du second facteur après le mot de passe (AUTH_HARDENING.md § 5.4). */
enum SecondFactorDecision
{
    /** Session ouverte : pas d'obligation, ou appareil de confiance. */
    case Pass;
    /** Session ouverte, avec une date d'échéance pour s'enrôler (AUTH-2FA-02). */
    case Grace;
    /** Pas de session : un code du second facteur actif est demandé (AUTH-2FA-07). */
    case Challenge;
    /** Pas de session : la grâce est passée, l'enrôlement est exigé (AUTH-2FA-02). */
    case EnrollmentRequired;
}
