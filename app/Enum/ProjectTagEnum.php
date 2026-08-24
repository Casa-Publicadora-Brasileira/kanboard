<?php

namespace Kanboard\Enum;

enum ProjectTagEnum: int
{
    case CPB_PROVAS = 233;
    case DEVOPS = 243;
    case ACES = 245;
    case GESTOR_CONTEUDO = 246;
    case GESTOR_OPERACOES = 247;
    case PROJETOS = 248;
    case SOLUCOES_EDUCACIONAIS = 249;

    public function label(): string
    {
        return match($this) {
            self::CPB_PROVAS => 'CPB Provas',
            self::DEVOPS => 'DevOps',
            self::ACES => 'ACES',
            self::GESTOR_CONTEUDO => 'Gestor de Conteúdo',
            self::GESTOR_OPERACOES => 'Gestor de Operações',
            self::PROJETOS => 'Projetos',
            self::SOLUCOES_EDUCACIONAIS => 'Soluções Educacionais',
        };
    }
}
