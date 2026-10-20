<?php

namespace App\Enum;

enum ClusterAnalysisSequenceType: string
{
    case UniProt = 'uniprot';
    case UniProtDomain = 'uniprot_domain';
    case UniRef90 = 'uniref90';
    case UniRef90Domain = 'uniref90_domain';
    case UniRef50 = 'uniref50';
    case UniRef50Domain = 'uniref50_domain';
}
