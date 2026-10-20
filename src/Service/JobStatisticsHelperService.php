<?php

namespace App\Service;

use App\Entity\CGFPResultsTrait;
use App\Entity\CGFPQuantifyJob;
use App\Entity\ColorSSNTrait;
use App\Entity\ExcludeFragmentsTrait;
use App\Entity\ESTGenerateAccessionJob;
use App\Entity\ESTGenerateBlastJob;
use App\Entity\ESTGenerateFastaJob;
use App\Entity\ESTGenerateJob;
use App\Entity\ESTSSNFinalizationJob;
use App\Entity\FilterByFamiliesTrait;
use App\Entity\FilterByTaxonomyTrait;
use App\Entity\Job;
use App\Entity\ProteinFamilyAdditionTrait;
use App\Entity\ResultStatsTrait;
use App\Entity\UserUploadedIdsTrait;
use App\Enum\SequenceType;

class JobStatisticsHelperService
{
    public function addResultsStats(Job $job, array $stats): void
    {
        if (array_key_exists(ESTGenerateJob::class, class_parents($job))) {
            $this->addESTGenerateTypeStats($job, $stats);
        }

        if ($this->classUsesTrait($job, ColorSSNTrait::class)) {
            $this->addClusterTypeSsnStats($job, $stats);
        }
        if ($this->classUsesTrait($job, ProteinFamilyAdditionTrait::class)) {
            $this->addFamilyTypeStats($job, $stats);
        }
        if ($this->classUsesTrait($job, UserUploadedIdsTrait::class)) {
            $this->addUserUploadTypeStats($job, $stats);
        }
        if ($this->classUsesTrait($job, ResultStatsTrait::class)) {
            $job->setResultFileStats(json_encode($stats['file_stats'] ?? []));
        }

        if (get_class($job) === ESTGenerateAccessionJob::class) {
            if (isset($stats['num_foreign'])) {
                $job->setNumForeignIds($stats['num_foreign']);
            }
        }

        if (get_class($job) === ESTGenerateBlastJob::class) {
            if (isset($stats['num_blast_retr'])) {
                $job->setNumBlastRetrievedIds($stats['num_blast_retr']);
            }
            if (isset($stats['num_blast_unmatched'])) {
                $job->setNumBlastUnmatchedIds($stats['num_blast_unmatched']);
            }
        }

        if (get_class($job) === ESTGenerateFastaJob::class) {
            if (isset($stats['num_headers'])) {
                $job->setNumFastaHeaders($stats['num_headers']);
            }
        }

        if ($this->classUsesTrait($job, ExcludeFragmentsTrait::class)) {
            if (isset($stats['num_filter_fragment'])) {
                $job->setNumFragmentFiltered($stats['num_filter_fragment']);
            }
        }
        if ($this->classUsesTrait($job, FilterByFamiliesTrait::class)) {
            if (isset($stats['num_filter_family'])) {
                $job->setNumFamilyFiltered($stats['num_filter_family']);
            }
        }
        if ($this->classUsesTrait($job, FilterByTaxonomyTrait::class)) {
            if (isset($stats['num_filter_taxonomy'])) {
                $job->setNumTaxonomyFiltered($stats['num_filter_taxonomy']);
            }
        }

        if ($this->classUsesTrait($job, CGFPResultsTrait::class)) {
            if (isset($stats['num_cdhit_clusters'])) {
                $job->setNumCdhitClusters($stats['num_cdhit_clusters']);
            }
            if (isset($stats['num_markers'])) {
                $job->setNumMarkers($stats['num_markers']);
            }
            if (isset($stats['num_unique_seq'])) {
                $job->setNumUniqueSeq($stats['num_unique_seq']);
            }
        }
        if ($job instanceof CGFPQuantifyJob) {
            if (isset($stats['num_cons_seq_with_hits'])) {
                $job->setnumConsensusSeqWithHits($stats['num_cons_seq_with_hits']);
            }
        }
    }

    private function addClusterTypeSsnStats(Job $job, array $stats): void
    {
        if (isset($stats['num_ssn_clusters'])) {
            $job->setNumSsnClusters($stats['num_ssn_clusters']);
        }
        if (isset($stats['num_ssn_singletons'])) {
            $job->setNumSsnSingletons($stats['num_ssn_singletons']);
        }
        if (isset($stats['num_ssn_nodes'])) {
            $job->setNumSsnMetanodes($stats['num_ssn_nodes']);
        }
        if (isset($stats['num_ssn_accession_ids'])) {
            $job->setNumSsnAccessionIds($stats['num_ssn_accession_ids']);
        }
        $job->setColorSsnSequenceSource($stats['ssn_sequence_source']);
    }

    private function addFamilyTypeStats(Job $job, array $stats): void
    {
        if (isset($stat['num_family'])) {
            $job->setNumFamilyIds($stats['num_family']);
        }
        if (isset($stats['num_full_family'])) {
            $job->setNumFullFamilyIds($stats['num_full_family']);
        }
        if (isset($stats['num_shared_ids'])) {
            $job->setNumFamilyOverlapIds($stats['num_shared_ids']);
        }
        if (isset($stats['num_filter_fraction'])) {
            $job->setNumFractionFiltered($stats['num_filter_fraction']);
        }
    }

    private function addUserUploadTypeStats(Job $job, array $stats): void
    {
        if (isset($stats['num_matched'])) {
            $job->setNumMatchedIds($stats['num_matched']);
        }
        if (isset($stats['num_unmatched'])) {
            $job->setNumUnmatchedIds($stats['num_unmatched']);
        }
    }

    private function addESTGenerateTypeStats(Job $job, array $stats): void
    {
        if (isset($stats['convergence_ratio'])) {
            $job->setOutputConvergenceRatio($stats['convergence_ratio']);
        }
        if (isset($stats['num_ids'])) {
            $job->setNumImportedIds($stats['num_ids']);
        }
        if (isset($stats['num_unique_ids'])) {
            $job->setNumComputedIds($stats['num_unique_ids']);
        }
        if (isset($stats['num_blast_edges'])) {
            $job->setNumBlastEdges($stats['num_blast_edges']);
        }
    }

    private function classUsesTrait(Job $job, string $traitName): bool
    {
        $traits = class_uses($job);
        return array_key_exists($traitName, $traits);
    }
}
