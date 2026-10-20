<?php

namespace App\Enum;

enum ClusterAnalysisFileType: string
{
    case ConsensusResidue = 'ca_cons_res';
    case Hmm = 'ca_hmm';
    case LengthHistogram = 'ca_length_histogram';
    case Msa = 'ca_msa';
    case Weblogo = 'ca_weblogo';
}
