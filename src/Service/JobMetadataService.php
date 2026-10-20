<?php

namespace App\Service;

use App\Config\Constants;
use App\Config\Settings;
use App\Entity\CGFPIdentifyJob;
use App\Entity\CGFPQuantifyJob;
use App\Entity\DisplayName;
use App\Entity\ESTClusterAnalysisJob;
use App\Entity\ESTColorSSNJob;
use App\Entity\ESTConvergenceRatioJob;
use App\Entity\ESTGenerateAccessionJob;
use App\Entity\ESTGenerateBlastJob;
use App\Entity\ESTGenerateFamiliesJob;
use App\Entity\ESTGenerateFastaJob;
use App\Entity\ESTGenerateJob;
use App\Entity\ESTNeighborhoodConnectivityJob;
use App\Entity\ESTSSNFinalizationJob;
use App\Entity\FamilyJobInterface;
use App\Entity\GNTDiagramBlastJob;
use App\Entity\GNTDiagramFastaJob;
use App\Entity\GNTDiagramJob;
use App\Entity\GNTDiagramSequenceIdJob;
use App\Entity\GNTGNNJob;
use App\Entity\GNTViewDiagramJob;
use App\Entity\Job;
use App\Entity\TaxonomyAccessionJob;
use App\Entity\TaxonomyFamiliesJob;
use App\Entity\TaxonomyFastaJob;
use App\Entity\TaxonomyJob;
use App\Enum\SequenceType;

class JobMetadataService
{
    private const METADATA_TYPE_SEQUENCE_PREVIEW = 'sequence_preview';

    public function __construct(
        private readonly ConfigurationManager $configManager,
    )
    {
    }

    /**
     * @return array<int, string|array{type: string, label: string, preview: string, tooltip: string}>
     */
    public function collectMetadata(Job $job): array
    {
        $metadata = [];

        $addDisplayName = true;

        if ($job instanceof CGFPIdentifyJob) {
            $this->addCGFPIdentifyJobMetadata($job, $metadata);
        } elseif ($job instanceof CGFPQuantifyJob) {
            $this->addCGFPQuantifyJobMetadata($job, $metadata);
        } elseif ($job instanceof ESTClusterAnalysisJob) {
            $this->addESTClusterAnalysisJobMetadata($job, $metadata);
        } elseif ($job instanceof ESTColorSSNJob) {
            $this->addESTColorSSNJobMetadata($job, $metadata);
        } elseif ($job instanceof ESTConvergenceRatioJob) {
            $this->addESTConvergenceRatioJobMetadata($job, $metadata);
        } elseif ($job instanceof ESTGenerateAccessionJob) {
            $this->addESTGenerateAccessionJobMetadata($job, $metadata);
        } elseif ($job instanceof ESTGenerateBlastJob) {
            $this->addESTGenerateBlastJobMetadata($job, $metadata);
        } elseif ($job instanceof ESTGenerateFamiliesJob) {
            $this->addESTGenerateFamiliesJobMetadata($job, $metadata);
        } elseif ($job instanceof ESTGenerateFastaJob) {
            $this->addESTGenerateFastaJobMetadata($job, $metadata);
        } elseif ($job instanceof ESTNeighborhoodConnectivityJob) {
            $this->addESTNeighborhoodConnectivityJobMetadata($job, $metadata);
        } elseif ($job instanceof ESTSSNFinalizationJob) {
            $this->addESTSSNFinalizationJobMetadata($job, $metadata);
            $addDisplayName = false;
        } elseif ($job instanceof GNTDiagramBlastJob) {
            $this->addGNTDiagramBlastJobMetadata($job, $metadata);
        } elseif ($job instanceof GNTDiagramFastaJob) {
            $this->addGNTDiagramFastaJobMetadata($job, $metadata);
        } elseif ($job instanceof GNTDiagramSequenceIdJob) {
            $this->addGNTDiagramSequenceIdJobMetadata($job, $metadata);
        } elseif ($job instanceof GNTGNNJob) {
            $this->addGNTGNNJobMetadata($job, $metadata);
            $addDisplayName = false;
        } elseif ($job instanceof GNTViewDiagramJob) {
            $this->addGNTViewDiagramJobMetadata($job, $metadata);
        } elseif ($job instanceof TaxonomyAccessionJob) {
            $this->addTaxonomyAccessionJobMetadata($job, $metadata);
        } elseif ($job instanceof TaxonomyFamiliesJob) {
            $this->addTaxonomyFamiliesJobMetadata($job, $metadata);
        } elseif ($job instanceof TaxonomyFastaJob) {
            $this->addTaxonomyFastaJobMetadata($job, $metadata);
        }

        if ($addDisplayName) {
            array_unshift($metadata, DisplayName::getFrom($job));
        }

        return $metadata;
    }

    private function addCGFPIdentifyJobMetadata(CGFPIdentifyJob $job, array &$metadata): void
    {
    }

    private function addCGFPQuantifyJobMetadata(CGFPQuantifyJob $job, array &$metadata): void
    {
    }

    private function addESTClusterAnalysisJobMetadata(ESTClusterAnalysisJob $job, array &$metadata): void
    {
        if ($job->getMakeHmms()) {
            $metadata[] = "HMMs";
        }
        if ($job->getMakeWeblogo()) {
            $metadata[] = "WebLogos";
        }
        if ($job->getMakeLengthHistograms()) {
            $metadata[] = "Length Histograms";
        }
        if ($job->getComputeConsensusResidues()) {
            if ($job->getConsensusResidues() !== null) {
                $metadata[] = "AAs=" . $job->getConsensusResidues();
            }
            if ($job->getPidThresholds() !== null) {
                $metadata[] = "Thresholds=" . implode(",", $job->getPidThresholds());
            }
        }
    }

    private function addESTColorSSNJobMetadata(ESTColorSSNJob $job, array &$metadata): void
    {
    }

    private function addESTConvergenceRatioJobMetadata(ESTConvergenceRatioJob $job, array &$metadata): void
    {
    }

    private function addESTGenerateAccessionJobMetadata(ESTGenerateAccessionJob $job, array &$metadata): void
    {
        $metadata[] = $job->getUploadedFilename();
        $this->addSharedESTGenerateMetadata($job, $metadata);
        $this->addTaxSearchMetadata($this->getFormattedTaxonomySearch($job->getTaxSearch(), $job->getTaxSearchName()), $metadata);
        $this->addExcludeFragmentsMetadata($job->isExcludeFragments(), $metadata);
    }

    private function addESTGenerateBlastJobMetadata(ESTGenerateBlastJob $job, array &$metadata): void
    {
        $blastEValue = $job->getBlastEValue();
        $maxBlastSequences = $job->getMaxBlastSequences();

        if ($blastEValue !== Constants::DEFAULT_BLAST_EVALUE) {
            $metadata[] = "Search E-value=$blastEValue";
        }
        if ($maxBlastSequences !== Constants::DEFAULT_MAX_BLAST_SEQUENCES) {
            $metadata[] = "max hits=$maxBlastSequences";
        }
        $this->addSharedESTGenerateMetadata($job, $metadata);
        $this->addSequenceMetadata($job->getJobFilename(), $metadata);
        $this->addTaxSearchMetadata($this->getFormattedTaxonomySearch($job->getTaxSearch(), $job->getTaxSearchName()), $metadata);
        $this->addExcludeFragmentsMetadata($job->isExcludeFragments(), $metadata);
    }

    private function addESTGenerateFamiliesJobMetadata(ESTGenerateFamiliesJob $job, array &$metadata): void
    {
        $domain = $job->getFormattedDomain();

        $this->addSharedESTGenerateMetadata($job, $metadata);
        $this->addExcludeFragmentsMetadata($job->isExcludeFragments(), $metadata);
        $this->addTaxSearchMetadata($this->getFormattedTaxonomySearch($job->getTaxSearch(), $job->getTaxSearchName()), $metadata);
        if ($domain) {
            $metadata[] = 'Domain: ' . $domain;
        }
    }

    private function addESTGenerateFastaJobMetadata(ESTGenerateFastaJob $job, array &$metadata): void
    {
        $metadata[] = $job->getUploadedFilename();
        $this->addSharedESTGenerateMetadata($job, $metadata);
    }

    private function addESTNeighborhoodConnectivityJobMetadata(ESTNeighborhoodConnectivityJob $job, array &$metadata): void
    {
    }

    private function addESTSSNFinalizationJobMetadata(ESTSSNFinalizationJob $job, array &$metadata): void
    {
        $thresholdValue = $job->getThresholdValue();
        $thresholdMetric = $job->getThresholdMetric()->getLabel();
        $minLength = $job->getMinLength();
        $maxLength = $job->getMaxLength();

        $metadata[] = "$thresholdMetric=$thresholdValue";
        if ($minLength && $minLength !== Constants::DEFAULT_MIN_LENGTH) {
            $metadata[] = "Min=$minLength";
        }
        if ($maxLength && $maxLength !== Constants::DEFAULT_MAX_LENGTH) {
            $metadata[] = "Max=$maxLength";
        }
        if ($job->isComputeNeighborhoodConnectivity()) {
            $metadata[] = "Neighborhood Connectivity";
        }
        $this->addExcludeFragmentsMetadata($job->isExcludeFragments(), $metadata);
        $this->addTaxSearchMetadata($this->getFormattedTaxonomySearch($job->getTaxSearch(), $job->getTaxSearchName()), $metadata);
    }

    private function addGNTDiagramBlastJobMetadata(GNTDiagramBlastJob $job, array &$metadata): void
    {
        $this->addNeighborhoodSizeMetadata($job->getNeighborhoodSize(), $metadata);
        $this->addUnirefVersion($job->getUnirefVersion(), $metadata);
        $this->addExcludeFragmentsMetadata($job->isExcludeFragments(), $metadata);
    }

    private function addGNTDiagramFastaJobMetadata(GNTDiagramFastaJob $job, array &$metadata): void
    {
        $this->addNeighborhoodSizeMetadata($job->getNeighborhoodSize(), $metadata);
    }

    private function addGNTDiagramSequenceIdJobMetadata(GNTDiagramSequenceIdJob $job, array &$metadata): void
    {
        $this->addNeighborhoodSizeMetadata($job->getNeighborhoodSize(), $metadata);
        $this->addUnirefVersion($job->getUnirefVersion(), $metadata);
        $this->addExcludeFragmentsMetadata($job->isExcludeFragments(), $metadata);
    }

    private function addGNTGNNJobMetadata(GNTGNNJob $job, array &$metadata): void
    {
        $this->addNeighborhoodSizeMetadata($job->getNeighborhoodSize(), $metadata);
        $metadata[] = "Co-occurrence=" . $job->getCooccurrence();
    }

    private function addNeighborhoodSizeMetadata(?int $nbSize, array &$metadata): void
    {
        if ($nbSize !== null) {
            $metadata[] = "Neighborhood Size=" . $nbSize;
        }
    }

    private function addGNTViewDiagramJobMetadata(GNTViewDiagramJob $job, array &$metadata): void
    {
    }

    private function addTaxonomyAccessionJobMetadata(TaxonomyAccessionJob $job, array &$metadata): void
    {
        $metadata[] = $job->getUploadedFilename();
        $this->addUnirefVersion($job->getUnirefVersion(), $metadata);
        $this->addExcludeFragmentsMetadata($job->isExcludeFragments(), $metadata);
    }

    private function addTaxonomyFamiliesJobMetadata(TaxonomyFamiliesJob $job, array &$metadata): void
    {
        if ($job instanceof FamilyJobInterface) {
            $this->addSharedFamilyMetadata($job, $metadata);
        }
        $this->addExcludeFragmentsMetadata($job->isExcludeFragments(), $metadata);
    }

    private function addTaxonomyFastaJobMetadata(TaxonomyFastaJob $job, array &$metadata): void
    {
        $metadata[] = $job->getUploadedFilename();
    }


    // Helper methods

    private function addSharedESTGenerateMetadata(ESTGenerateJob $job, array &$metadata): void
    {
        $evalue = $job->getAllByAllBlastEValue();

        if ($evalue !== Constants::DEFAULT_ALL_BY_ALL_BLAST_EVALUE) {
            $metadata[] = "BLAST E-value: " . $evalue;
        }
        if ($job instanceof FamilyJobInterface) {
            $this->addSharedFamilyMetadata($job, $metadata);
        }
    }

    private function addSharedFamilyMetadata(FamilyJobInterface $job, array &$metadata): void
    {
        $families = $job->getFormattedFamilies();
        $fraction = $job->getFraction();

        if ($families) {
            $trimmedFamily = $families;
            if (strlen($families) > 16) {
                $trimmedFamily = substr($families, 0, 17) . "...";
            }
            $metadata[] = $trimmedFamily;
        }
        if ($fraction && $fraction !== Constants::DEFAULT_FRACTION) {
            $metadata[] = "Fraction: " . $fraction;
        }
        $this->addUnirefVersion($job->getUnirefVersion(), $metadata);
    }

    private function addUnirefVersion(?SequenceType $unirefVersion, array &$metadata): void
    {
        if ($unirefVersion !== null && $unirefVersion !== SequenceType::UniProt) {
            $metadata[] = $unirefVersion->getLabel();
        }
    }

    private function addTaxSearchMetadata(?string $taxSearch, array &$metadata): void
    {
        if ($taxSearch) {
            $metadata[] = "Taxonomy filtering: " . $taxSearch;
        }
    }

    private function addExcludeFragmentsMetadata(?bool $excludeFragments, array &$metadata): void
    {
        if ($excludeFragments) {
            $metadata[] = "Fragments: no";
        } else {
            $metadata[] = "Fragments: yes";
        }
    }

    private function addSequenceMetadata(?string $jobFilename, array &$metadata): void
    {
        if (!$jobFilename) {
            return;
        }

        $path = $this->configManager->get(Settings::UPLOADS_DIRECTORY);
        $path = "$path/$jobFilename";

        if (!file_exists($path)) {
            return;
        }

        $sequence = file_get_contents($path);

        if (!$sequence) {
            return;
        }

        $label = substr($sequence, 0, 20);
        $title = "";
        $pos = 0;
        while ($pos < strlen($sequence)) {
            $title .= substr($sequence, $pos, 27) . "\n";
            $pos += 40;
        }

        $metadata[] = [
            'type' => self::METADATA_TYPE_SEQUENCE_PREVIEW,
            'label' => 'Sequence',
            'preview' => $label . '...',
            'tooltip' => rtrim($title),
        ];
    }

    private function getFormattedTaxonomySearch(?array $taxSearch, ?string $taxSearchName): string
    {
        if ($taxSearchName) {
            return $taxSearchName;
        } else if ($taxSearch) {
            return "Custom";
        } else {
            return "";
        }
    }
}
