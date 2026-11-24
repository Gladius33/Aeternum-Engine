<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

/**
 * UI-agnostic form specification: quick and detailed modes use the same
 * canonical fields as curated real-person biographies.
 */
final class PoliticalProfileTemplate
{
    public const VERSION = '1.0.0';
    public const AVATAR = 'generic-v1';

    /** @return array<string,mixed> */
    public static function definition(): array
    {
        $template=[
            'schema_version' => self::VERSION,
            'avatar' => ['key' => self::AVATAR, 'uploads_enabled' => false],
            'modes' => [
                'quick' => ['required' => ['name'], 'optional' => ['biography', 'programme']],
                'detailed' => ['required' => ['name'], 'optional' => ['biography', 'programme', 'profile']],
            ],
            'sections' => [
                ['id'=>'identity','label'=>'Identité et présentation','fields'=>['name','biography','birth_date','birth_place','personal_statement']],
                ['id'=>'education','label'=>'Études et formation','fields'=>['education','languages']],
                ['id'=>'career','label'=>'Carrière et haute administration','fields'=>['professional_history','public_service','expertise']],
                ['id'=>'politics','label'=>'Engagements politiques','fields'=>['party','orientation','political_history']],
                ['id'=>'programme','label'=>'Programme politique','fields'=>['programme','policy_priorities']],
            ],
            'fields' => [
                'name'=>['type'=>'text','required'=>true,'max_length'=>160],
                'biography'=>['type'=>'long_text','required'=>false,'max_length'=>2000],
                'birth_date'=>['type'=>'date','required'=>false],
                'birth_place'=>['type'=>'text','required'=>false,'max_length'=>160],
                'personal_statement'=>['type'=>'long_text','required'=>false,'max_length'=>2000],
                'education'=>['type'=>'text_list','required'=>false,'max_items'=>24],
                'languages'=>['type'=>'text_list','required'=>false,'max_items'=>24],
                'professional_history'=>['type'=>'text_list','required'=>false,'max_items'=>24],
                'public_service'=>['type'=>'text_list','required'=>false,'max_items'=>24],
                'expertise'=>['type'=>'text_list','required'=>false,'max_items'=>24],
                'party'=>['type'=>'text','required'=>false,'max_length'=>160],
                'orientation'=>['type'=>'text','required'=>false,'max_length'=>160],
                'political_history'=>['type'=>'text_list','required'=>false,'max_items'=>24],
                'programme'=>['type'=>'measure_list','required'=>false,'max_items'=>100],
                'policy_priorities'=>['type'=>'text_list','required'=>false,'max_items'=>24],
            ],
        ];
        $labels=[
            'name'=>['Nom du personnage','Seul champ obligatoire. Un identifiant technique est généré par le serveur.'],
            'biography'=>['Présentation générale','Qui est votre personnage ? Résumez son histoire et ses motivations.'],
            'birth_date'=>['Date de naissance','Facultatif. Utilisez une date cohérente avec votre scénario.'],
            'birth_place'=>['Lieu de naissance','Ville, région ou autre lieu fictif ou réel.'],
            'personal_statement'=>['Déclaration personnelle','Présentez sa vision du pouvoir et ses objectifs.'],
            'education'=>['Formation et diplômes','Ajoutez autant de formations que vous le souhaitez, dans les limites prévues.'],
            'languages'=>['Langues','Langues pratiquées dans votre histoire alternative.'],
            'professional_history'=>['Parcours professionnel','Emplois, responsabilités et étapes de carrière.'],
            'public_service'=>['Carrière administrative','Fonctions dans la haute administration ou les services publics.'],
            'expertise'=>['Domaines d’expertise','Domaines d’expérience déclarés par ce personnage.'],
            'party'=>['Parti politique','Parti existant dans le scénario ou organisation fictive.'],
            'orientation'=>['Courant politique revendiqué','Description libre, sans assignation automatique par le jeu.'],
            'political_history'=>['Mandats et engagements','Expérience politique antérieure du personnage fictif.'],
            'programme'=>['Programme électoral','Ajoutez des propositions concrètes, classées par domaine. Ce champ peut rester vide.'],
            'policy_priorities'=>['Priorités gouvernementales','Domaines que le personnage souhaite mettre en avant.'],
        ];
        foreach ($labels as $id=>$text) {
            $template['fields'][$id]['label']=$text[0];
            $template['fields'][$id]['help']=$text[1];
        }
        $template['locale']='fr-FR';
        return $template;
    }

    /**
     * Server-owned, retry-stable identifier. Not an authentication secret.
     * Never accept principal or idempotency source from form-body fields.
     */
    public static function generatedId(string $actor,string $world,string $country,string $key):string
    {
        return 'f-'.substr(hash('sha256',$actor."\n".$world."\n".$country."\n".$key),0,32);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function normalizeFictional(array $input, string $country): array
    {
        if (!preg_match('/^[A-Z]{2}$/D',$country)
            || (array_is_list($input) && $input !== [])) {
            throw new \InvalidArgumentException('Invalid character object');
        }
        foreach (array_keys($input) as $key) {
            if (!in_array($key,['id','name','country','kind','biography','programme','as_of','sources','profile','avatar','mode'],true)) {
                throw new \InvalidArgumentException('Unexpected field');
            }
        }
        if (($input['kind'] ?? 'fictional') !== 'fictional'
            || ($input['country'] ?? $country) !== $country
            || ($input['as_of'] ?? null) !== null
            || ($input['sources'] ?? []) !== []
            || ($input['avatar'] ?? self::AVATAR) !== self::AVATAR
            || !in_array(($input['mode'] ?? 'quick'),['quick','detailed'],true)) {
            throw new \InvalidArgumentException('Invalid fictional provenance or avatar');
        }
        if (!isset($input['id'],$input['name'])) {
            throw new \InvalidArgumentException('Id and name required');
        }
        $profile=$input['profile'] ?? [];
        self::validateProfile($profile);
        $programme=$input['programme'] ?? [];
        if (!is_array($programme) || !array_is_list($programme) || count($programme)>100) {
            throw new \InvalidArgumentException('Invalid programme');
        }
        foreach ($programme as &$measure) {
            if (!is_array($measure) || (array_is_list($measure) && $measure!==[])) {
                throw new \InvalidArgumentException('Invalid proposal');
            }
            $measure['sources']=$measure['sources'] ?? [];
            if ($measure['sources']!==[]) {
                throw new \InvalidArgumentException('Fictional proposal cannot claim factual sources');
            }
        }
        unset($measure);
        return [
            'id'=>$input['id'], 'kind'=>'fictional','name'=>$input['name'],
            'country'=>$country, 'biography'=>$input['biography'] ?? '',
            'as_of'=>null,'sources'=>[],'programme'=>$programme,
            'profile'=>$profile,'avatar'=>self::AVATAR,
        ];
    }

    public static function validateProfile(mixed $profile): void
    {
        if (!is_array($profile) || (array_is_list($profile) && $profile!==[]) || count($profile)>12) {
            throw new \InvalidArgumentException('Invalid profile');
        }
        $texts=['birth_place'=>160,'personal_statement'=>2000,'party'=>160,'orientation'=>160];
        $lists=['education','languages','professional_history','public_service','expertise','political_history','policy_priorities'];
        foreach($profile as $key=>$value) {
            if ($key==='birth_date') {
                if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)
                    || ($date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value))===false
                    || $date->format('Y-m-d')!==$value) {
                    throw new \InvalidArgumentException('Invalid birth date');
                }
            } elseif (isset($texts[$key])) {
                self::checkText($value,$texts[$key]);
            } elseif (in_array($key,$lists,true)) {
                if (!is_array($value) || !array_is_list($value) || count($value)>24) {
                    throw new \InvalidArgumentException('Invalid list');
                }
                foreach($value as $entry) self::checkText($entry,300);
            } else {
                throw new \InvalidArgumentException('Unrecognized biographical field');
            }
        }
    }

    private static function checkText(mixed $value,int $max): void
    {
        if (!is_string($value) || strlen($value)>$max
            || preg_match('/[\x00-\x1f\x7f]/',$value)) {
            throw new \InvalidArgumentException('Invalid text');
        }
    }
}
