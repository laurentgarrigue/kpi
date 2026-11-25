<?php

namespace App\ClubRanking\Calculator;

use App\ClubRanking\Model\Alert;
use App\ClubRanking\Model\Category;
use App\ClubRanking\Model\Line;
use App\ClubRanking\Model\RankingSlot;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\RuleType;
use App\ClubRanking\Model\Scale;

/**
 * Results (7.4, § 5.6): the teams at a given place of the published final ranking (or of the regular season) of a
 * category. By default (`beneficiary: licensee_clubs`, annex 2026, I-7) every club having at least one player of the
 * team earns the points once, selections included; `team_club` pays the club of the team and its entente.
 * Tied teams each earn the points of their place (I-10). An unpublished or unlocked ranking blocks its category.
 */
final class RankingCalculator implements RuleCalculator
{
    public const BENEFICIARY_TEAM_CLUB = 'team_club';

    public function type(): RuleType
    {
        return RuleType::Ranking;
    }

    public function validate(Rule $rule, Scale $scale): array
    {
        $slots = RankingSlot::fromRule($rule);
        if ($slots === []) {
            return [sprintf('%s : aucune place à récompenser.', $rule->code())];
        }
        $errors = [];
        foreach ($slots as $slot) {
            if ($slot->place < 1) {
                $errors[] = sprintf('%s : place invalide.', $rule->code());
            }
            $errors = [...$errors, ...CategoryParam::validateCodes($rule, $scale, $slot->categories)];
            foreach ($slot->categories as $code) {
                $category = $scale->category($code);
                if ($category !== null && $category->rankingCompetition($slot->ranking) === null) {
                    $errors[] = sprintf('%s : la catégorie « %s » n\'a pas de compétition de %s.', $rule->code(), $code, $slot->ranking === RankingSlot::REGULAR ? 'saison régulière' : 'classement final');
                }
            }
        }

        return $errors;
    }

    public function compute(Rule $rule, CalculationContext $context): CalculationResult
    {
        $lines = [];
        $alerts = [];
        foreach (RankingSlot::fromRule($rule) as $slot) {
            foreach ($slot->categories as $code) {
                $category = $context->scale->category($code);
                if ($category !== null) {
                    $this->slotOfCategory($rule, $context, $slot, $category, $lines, $alerts);
                }
            }
        }

        return new CalculationResult($lines, $alerts);
    }

    /**
     * @param list<Line>  $lines
     * @param list<Alert> $alerts
     */
    private function slotOfCategory(Rule $rule, CalculationContext $context, RankingSlot $slot, Category $category, array &$lines, array &$alerts): void
    {
        $code = $category->rankingCompetition($slot->ranking);
        $competition = $code === null ? null : $context->data->competition($code);
        if ($competition === null) {
            // One alert per category, whatever the number of 7.4 rules reading it (no rule code: the engine merges them).
            $alerts[] = new Alert(Alert::COMPETITION_MISSING, sprintf('%s : compétition %s absente de la saison, catégorie non calculée.', $category->label, $code ?? '?'), context: ['category' => $category->code]);

            return;
        }
        if (!$competition->published || !$competition->locked) {
            $alerts[] = new Alert(Alert::RANKING_NOT_PUBLISHED, sprintf('%s : classement de %s non %s, catégorie non calculée.', $category->label, $competition->code, $competition->published ? 'verrouillé' : 'publié'), blocking: true, context: ['category' => $category->code, 'competition' => $competition->code]);

            return;
        }
        $teams = $context->teamsOf($category);
        $beneficiaryIsTeamClub = $rule->string('beneficiary', 'licensee_clubs') === self::BENEFICIARY_TEAM_CLUB;
        foreach ($context->data->teamEntries as $entry) {
            if ($entry->competition !== $competition->code || $entry->place($competition) !== $slot->place) {
                continue;
            }
            $entries = $teams[$entry->teamNumber] ?? [$entry];
            $clubs = $beneficiaryIsTeamClub ? $context->clubsOfTeam($entries) : $context->clubsOfPlayers($entries);
            $reason = sprintf('%s — %de %s%s', $entry->label, $slot->place, $category->label, $slot->ranking === RankingSlot::REGULAR ? ' (saison régulière)' : '');
            foreach ($clubs as $club) {
                $lines[] = new Line($rule->code(), $club, $rule->points, $reason, Line::SOURCE_TEAM, $entry->teamNumber . ':' . $competition->code);
            }
        }
    }
}
