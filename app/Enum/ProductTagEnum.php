<?php

namespace Kanboard\Enum;

enum ProductTagEnum: int
{
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

    public function label(): string
    {
        return match($this) {
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
        };
    }
}
