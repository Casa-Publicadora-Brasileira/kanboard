<?php

namespace Kanboard\Enum;

enum ProductTagEnum: int
{
    case ENEM_INTERATIVO = 223;
    case ECLASS = 232;
    case CPB_PROVAS = 233;
    case PROVAS_DIAGNOSTICAS = 234;
    case ACADEMICO = 235;
    case SKY_ENGLISH = 236;
    case SITE_ESCOLA = 237;
    case EPLAN = 238;
    case CURSOS = 239;
    case MATERIAIS_DIDATICOS = 240;
    case HUB = 241;
    case SITES = 242;
    case DEVOPS = 243;
    case DOCUMENTACAO = 244;
    case CRON = 252;
    case VOUCHER = 253;
    case REDACAO = 254;
    case ORIGENS = 255;
    case ENCONTRE_UMA_ESCOLA = 256;
    case EDUCACAO_ADVENTISTA = 257;
    case SISTEMA_INTERATIVO = 258;
    case SABADO_DA_CRIACAO = 259;
    case SALA_DE_PROFESSORES = 260;
    case ESCHOOL = 261;
    case CPB_EDUCACIONAL = 263;

    public function label(): string
    {
        return match($this) {
            self::ENEM_INTERATIVO => 'ENEM Interativo',
            self::ECLASS => 'E-Class',
            self::CPB_PROVAS => 'CPB Provas',
            self::PROVAS_DIAGNOSTICAS => 'Provas Diagnósticas',
            self::ACADEMICO => 'Acadêmico',
            self::SKY_ENGLISH => 'Sky English',
            self::SITE_ESCOLA => 'Site Escola',
            self::EPLAN => 'E-Plan',
            self::CURSOS => 'Cursos',
            self::MATERIAIS_DIDATICOS => 'Materiais didáticos',
            self::HUB => 'Hub',
            self::SITES => 'Sites',
            self::DEVOPS => 'DevOps',
            self::DOCUMENTACAO => 'Documentação',
            self::CRON => 'Cron',
            self::VOUCHER => 'Voucher',
            self::REDACAO => 'Redação',
            self::ORIGENS => 'Origens',
            self::ENCONTRE_UMA_ESCOLA => 'Encontre uma Escola',
            self::EDUCACAO_ADVENTISTA => 'Educação Adventista',
            self::SISTEMA_INTERATIVO => 'Sistema Interativo',
            self::SABADO_DA_CRIACAO => 'Sábado da Criação',
            self::SALA_DE_PROFESSORES => 'Sala de Professores',
            self::ESCHOOL => 'Eclass School',
            self::CPB_EDUCACIONAL => 'CPB Educacional',
        };
    }

    /**
     * Retorna todos os casos formatados em array [id, name]
     */
    public static function toArray(): array
    {
        return array_map(fn (self $case) => [
            'id'   => $case->value,
            'name' => $case->label(),
        ], self::cases());
    }
}
