<?php

namespace App\Service;

use App\Entity\Job;
use App\Entity\ResultStatsTrait;
use App\Enum\SequenceType;
use App\Dto\ParentTransferRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

class ParentTransferService
{
    public function __construct(
        private readonly JobRetrieverService $jobRetrieverService,
        private readonly LoggerInterface $logger,
        private readonly SSNResultStatsService $statsService,
    )
    {
    }

    /**
     * Get options necessary to populate forms when transferring from another job.  For example,
     * a SSN finalization job can be transferred to GNT GNN.  The parameters from this function
     * can be provided directly to App\Form\Type\ParentJobReferenceType as options.
     * @param {Request} $request - HTTP request
     * @return {?array} an array representing options that can be directly passed into
     *     the configuration of ParentJobReferenceType; will be null if no or invalid request
     */
    public function getParentTransferRequestOptions(Request $request): ?array
    {
        $transferData = $this->getTransferData($request);

        if (!$transferData
            || !$transferData['parentId']
            || !$transferData['parentKeyStr']
            || !is_numeric($transferData['parentId'])
            || !is_string($transferData['parentKeyStr'])
        ) {
            return null;
        }

        $transferData['parentId'] = (int) $transferData['parentId'];

        try {
            $transferData['parentKey'] = Uuid::fromString($transferData['parentKeyStr']);
        } catch (\InvalidArgumentException) {
            return null;
        }

        if ($transferData['parentSubtype'] && (!is_string($transferData['parentSubtype']) || !preg_match('/^(full|repnode)?[0-9_-]*$/', $transferData['parentSubtype']))) { // e.g. repnode_90, or 70
            return null;
        }

        if ($transferData['subtypeName'] && (!is_string($transferData['subtypeName']) || !preg_match('/^[A-Za-z0-9\._-]+$/', $transferData['subtypeName']))) { // e.g. 'Ascomycota'
            $transferData['subtypeName'] = null;
        }

        $job = $this->jobRetrieverService->findJob($transferData['parentId'], $transferData['parentKey']);
        if (!$job) {
            return null;
        }

        $jobName = $job->getJobName();
        if ($jobName) {
            $jobName = htmlspecialchars((string) $jobName, ENT_QUOTES, 'UTF-8');
        }

        $suggestedName = $jobName ?? '';
        if ($transferData['subtypeName']) {
            $suggestedName .= ($suggestedName ? " - " : "") . $transferData['subtypeName'];
        }
        if ($transferData['parentSeqType']) {
            $suggestedName .= ($suggestedName ? " - " : "") . $transferData['parentSeqType']->getLabel();
        }

        $parentSequences = $this->getParentSequences($request, $transferData);

        $subtype = $transferData['parentSubtype'] ? ' ' . $transferData['parentSubtype'] : '';
        $metadata = sprintf("Using job %d %s as the input for this job", $transferData['parentId'], $jobName ? "(<i>$jobName$subtype</i>)" : "");

        $transferRequest = new ParentTransferRequest(
            target_form: $transferData['targetForm'],
            parent_id: $transferData['parentId'],
            parent_key: $transferData['parentKey'],
            parent_subtype: $transferData['parentSubtype'] ?? null,
            parent_metadata: $metadata,
            subtype_name: $transferData['subtypeName'],
            parent_seq_type: $transferData['parentSeqType'],
            suggested_name: $suggestedName,
            parent_sequences: $parentSequences,
        );

        return $transferRequest?->toFormOptions();
    }

    /**
     * Finalizes the transfer of an SSN to a SSN utility job.  This looks up the subtype and gets
     * the filename to store in the sourceFileReference, as well as sets the uploaded filename and
     * adds the parent reference.
     * @param {Job} $job - the new job
     * @param {int} $parentId - the ID of the parent job
     * @param {string} $parentUuid - the UUID key of the parent job
     * @param {bool} $subtypeRequired - true if the job requires a subtype (e.g. full, repnode)
     * @param {?string} $subtype - subtype name (e.g. full)
     * @return {bool} true if transferred, false if error
     */
    public function transferJob(Job $job, int $parentId, string $parentUuid, bool $subtypeRequired, ?string $subtype): bool
    {
        $parentJob = $this->jobRetrieverService->findJob($parentId, $parentUuid);

        if (!$parentJob) {
            $this->logger->error('Invalid source job.  Unable to find parent.', [
                'job_id' => $job->getId(),
                'job_parent_id' => $parentId,
                'job_parent_uuid' => $parentUuid,
                'subtype_required' => $subtypeRequired,
                'subtype' => $subtype,
            ]);
            return false;
        }

        if ($subtypeRequired) {
            $sourceRef = $this->getSourceRef($subtype, $parentJob);
            if (!$sourceRef) {
                $this->logger->error('Invalid source job.  Unable to make source reference.', [
                    'job_id' => $job->getId(),
                    'job_parent_id' => $parentId,
                    'job_parent_uuid' => $parentUuid,
                    'subtype_required' => $subtypeRequired,
                    'subtype' => $subtype,
                ]);
                return false;
            }

            $filename = sprintf("Transfer from %s (#%d)", $parentJob->getJobName(), $parentJob->getId());
            $job->setUploadedFilename($filename);
            $job->setSourceFileReference($sourceRef);
        }

        $parentJob->addChildJob($job);

        $job->setDbVersion($parentJob->getDbVersion());
        $job->setDbMetadata($parentJob->getDbMetadata());

        return true;
    }

    /**
     * Get the transferrable source reference name.  The source ref is a filename that is stored
     * on the backend.  The transfer ID is the string that corresponds to the source file type;
     * this is so we don't pass filenames through form submissions.  We look up the source ref
     * name in the parent job.
     * @param {string} $transferId - full, repnode-100, etc
     * @param {Job} $job - parent job
     * @return {string} file name on the backend
     */
    private function getSourceRef(string $transferId, Job $job): ?string
    {
        if (!$this->classUsesTrait($job, ResultStatsTrait::class)) {
            $this->logger->error('Job does not use ResultStatsTrait', [
                'job_id' => $job->getId(),
                'transfer_id' => $transferId,
            ]);
            return null;
        }

        $stats = $this->statsService->loadStats($job);

        $sourceRef = null;

        foreach ($stats as $fileName => $fileStats) {
            if ($transferId === $fileStats['type']) {
                $sourceRef = $fileStats['name'];
                break;
            }
        }

        if ($sourceRef) {
            $sourceRef = "$sourceRef.zip";
        }

        return $sourceRef;
    }

    /**
     * This gets the transfer parameters necessary to populate a transfer request object.  It
     * handles three cases: 1) when parameters are passed via a GET request (submitting to the
     * SSN utilities or GNN), 2) when parameters are passed via a POST request (submitting via
     * sunburst to GND or EST Accession), and 3) when parameters are submitted via POST upon job
     * form submission.
     * @param {Request} $request - request data (GET or POST)
     * @return {?array} null if no target form specified, array otherwise
     */
    private function getTransferData(Request $request): ?array
    {
        $data = [];

        // First try to get the parameters from the GET query
        $data['parentId'] = $request->query->get('parent_id') ?? null;
        $data['parentKeyStr'] = $request->query->get('parent_key') ?? null;
        $data['parentSubtype'] = $request->query->get('parent_subtype') ?? null;
        $data['subtypeName'] = $request->query->get('parent_subtype_name') ?? null;
        $parentSeqType = $request->query->get('parent_seq_type') ?? null;

        // Always require the targetForm to be specified with GET
        $data['targetForm'] = $request->query->get('f') ?? null;
        if (!is_string($data['targetForm']) || !preg_match('/^[A-Za-z]+$/', $data['targetForm'])) { // e.g. estBlastForm
            return null;
        }

        // This case handles when the user actually submits a new transfer job.  We need to
        // look at POST to get the ID and key instead of the query string.
        if ($request->isMethod('POST') && $data['parentId'] === null) {
            $postData = $request->request->all(); // Raw POST query

            // Sometimes (e.g. sunburst transfer to EST accession or GND sequence IDs) these
            // values are only specified in a normal POST request.
            $data['parentId'] = $postData['parent_id'] ?? null;
            $data['parentKeyStr'] = $postData['parent_key'] ?? null;
            $data['parentSubtype'] = $postData['parent_subtype'] ?? null;
            $data['subtypeName'] = $postData['parent_subtype_name'] ?? null;
            $parentSeqType = $postData['parent_seq_type'] ?? null;

            // Handle the case when the request is submitted when creating the job rather than
            // just initializing the form.
            foreach ($postData as $formName => $formData) {
                if (is_array($formData) && isset($formData['parent_reference'])) {
                    $data['parentId'] = $formData['parent_reference']['parent_id'] ?? null;
                    $data['parentKeyStr'] = $formData['parent_reference']['parent_key'] ?? null;
                    $data['parentSubtype'] = $formData['parent_reference']['parent_subtype'] ?? null;
                    $data['subtypeName'] = $formData['parent_reference']['parent_subtype_name'] ?? null;
                    $parentSeqType = $formData['parent_reference']['parent_seq_type'] ?? null;
                    break;
                }
            }
        }

        if ($parentSeqType) {        
            $data['parentSeqType'] = SequenceType::tryFrom($parentSeqType) ?? SequenceType::UniProt;
        } else {
            $data['parentSeqType'] = null;
        }

        return $data;
    }

    /**
     * Returns a string containing the list of IDs to populate the input request form form field.
     * This is used to transfer IDs from sunbursts to GND Sequence IDs or EST Accession.
     * @param {Request} $request - HTTP request
     * @param {array} $transferData - array containing data transferred to the form
     * @return {string} a new-line separated string of IDs
     */
    private function getParentSequences(Request $request, array $transferData): ?string
    {
        if (!$request->isMethod('POST')) {
            return null;
        }

        $fieldValue = '';

        $idList = $request->request->get('idList') ?? null;

        if ($idList) {
            $idList = preg_replace('/[^A-Za-z0-9_,\.-]/', '', $idList);
            if ($idList) {
                $lines = explode(',', $idList);
                $fieldValue = implode("\n", $lines);
            }
        }

        return $fieldValue;
    }

    private function classUsesTrait(Job $job, string $traitName)
    {
        $traits = class_uses($job);
        return array_key_exists($traitName, $traits);
    }
}
