<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Dated public calendar and deterministic game scheduling. Future calculated
 * dates are never represented as official announcements. Day-level handover
 * uses the first day after the incumbent's last day in office. */
final class FrenchElectionCalendar
{
    public const VERSION='wp54r-fr-election-calendar-2026-11-v1';
    public const PRESIDENTIAL_TERM_START='2022-05-14';
    public const PRESIDENTIAL_HANDOVER='2027-05-14';
    public const CANDIDACY_LOCK='2027-03-12';
    public static function report():array
    {
        return ['version'=>self::VERSION,'as_of'=>'2026-11-26','country'=>'FR',
            'sources'=>['constitution'=>'https://www.legifrance.gouv.fr/loda/texte_lc/LEGITEXT000006071194/',
                'elections'=>'https://www.service-public.gouv.fr/particuliers/vosdroits/F1939',
                'presidential_calendar'=>'https://www.info.gouv.fr/grand-dossier/chacun-pour-tous/election-presidentielle-2027-comment-participer-au-vote',
                'legislature_expiry'=>'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000006353288'],
            'presidential'=>['term_years'=>5,'max_consecutive_terms'=>2,'first_round'=>'2027-04-18','second_round'=>'2027-05-02',
                'last_day_in_office'=>'2027-05-13','game_handover_on'=>self::PRESIDENTIAL_HANDOVER,'sponsorship_deadline'=>self::CANDIDACY_LOCK,
                'candidate_list_latest'=>'2027-03-26','campaign_first_round_start'=>'2027-04-05','status'=>'announced','scope'=>'metropolitan_round_dates'],
            'legislative'=>['last_election_year'=>2024,'ordinary_expiry'=>self::assemblyExpiry(2024),'next_year'=>2029,
                'status'=>'year_announced_exact_poll_not_announced','game_schedule'=>self::legislativePoll()],
            'regional'=>['next_month'=>'2028-03','status'=>'month_announced'],
            'departmental'=>['next_month'=>'2028-03','status'=>'month_announced'],
            'european'=>['next_year'=>2029,'status'=>'year_announced'],
            'municipal'=>['next_year'=>2032,'status'=>'year_announced'],
            'senate'=>['last_month'=>'2026-09','next_ordinary_year'=>2029,'status'=>'derived_from_triennial_partial_renewal'],
            'republican_form_amendable_under_article_89'=>false];
    }
    public static function presidentialPoll(string $handover):array
    {
        if($handover===self::PRESIDENTIAL_HANDOVER)return ['first'=>'2027-04-18','second'=>'2027-05-02','candidacy_lock'=>self::CANDIDACY_LOCK,'status'=>'announced'];
        $end=new \DateTimeImmutable($handover);$first=$end->modify('-25 days');
        while($first->format('N')!=='7')$first=$first->modify('-1 day');
        return ['first'=>$first->format('Y-m-d'),'second'=>$first->modify('+14 days')->format('Y-m-d'),
            'candidacy_lock'=>$first->modify('-37 days')->format('Y-m-d'),'status'=>'scenario_calculated_not_announced'];
    }
    public static function assemblyExpiry(int $electedYear):string
    {
        if($electedYear<1958||$electedYear>2150)throw new \DomainException('Invalid Assembly election year');
        $d=new \DateTimeImmutable(($electedYear+5).'-06-01');
        while($d->format('N')!=='2')$d=$d->modify('+1 day');
        return $d->modify('+14 days')->format('Y-m-d');
    }
    public static function legislativePoll():array
    {
        return ['first'=>'2029-06-03','second'=>'2029-06-10','status'=>'scenario_calculated_not_announced'];
    }
}
