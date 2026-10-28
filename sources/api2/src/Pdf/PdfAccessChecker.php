<?php

namespace App\Pdf;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Who may get an admin PDF (PDF_GOTENBERG.md § 6.4): the minimum role of the document and of its options, checked
 * through the mandate-aware voter, then the perimeter of the active mandate (seasons, competitions, clubs). The
 * profiles 1–2 have no perimeter (User::getAllowed*() return null).
 */
class PdfAccessChecker
{
    public function __construct(private readonly Security $security)
    {
    }

    public function canGet(string $document, PdfRequest $request): bool
    {
        return $this->isAllowed($document, $request->options, $request->season, $request->competition);
    }

    /** @param array<string, string> $options */
    public function isAllowed(string $document, array $options, ?string $season, ?string $competition): bool
    {
        $role = PdfCatalog::adminRole($document);
        if ($role === null || !$this->security->isGranted($role)) {
            return false;
        }
        foreach (PdfCatalog::optionRoles($document, $options) as $optionRole) {
            if (!$this->security->isGranted($optionRole)) {
                return false;
            }
        }

        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return ($season === null || self::allows($user->getAllowedSeasons(), $season))
            && ($competition === null || self::allows($user->getAllowedCompetitions(), $competition));
    }

    /**
     * Clubs whose teams the user may see, or null when not limited (profile 7, PDF-AUTH-04).
     *
     * @return string[]|null
     */
    public function allowedClubs(): ?array
    {
        return $this->user()?->getAllowedClubs();
    }

    /**
     * Gamedays the user may see, or null when not limited (filter of the mandate).
     *
     * @return int[]|null
     */
    public function allowedGamedays(): ?array
    {
        return $this->user()?->getAllowedJournees();
    }

    /** @param string[]|null $allowed */
    private static function allows(?array $allowed, string $value): bool
    {
        return $allowed === null || in_array($value, $allowed, true);
    }

    private function user(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
