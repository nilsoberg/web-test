<?php

namespace App\Config;

class Constants
{
    public const DEFAULT_MIN_LENGTH = 0;

    public const DEFAULT_MAX_LENGTH = 65000;

    // For all EST jobs, all-by-all BLAST
    public const DEFAULT_ALL_BY_ALL_BLAST_EVALUE = 5;

    // For all EST jobs, all-by-all BLAST
    public const DEFAULT_MAX_ALL_BY_ALL_BLAST_SEQUENCES = 10000;

    // For EST BLAST job type
    public const DEFAULT_BLAST_EVALUE = 5;

    // For EST BLAST job type
    public const DEFAULT_MAX_BLAST_SEQUENCES = 1000;

    // For GND BLAST job type
    public const DEFAULT_GND_BLAST_SEQUENCES = 200;
    public const DEFAULT_MAX_GND_BLAST_SEQUENCES = 500;

    public const DEFAULT_FRACTION = 1;

    public const DEFAULT_TEXT_DOWNLOAD_EXTENSION = "tab";

    public const DEFAULT_MIN_NUM_SEQUENCES = 5;

    public const DEFAULT_MAX_NUM_SEQUENCES = 1000;

    public const DEFAULT_ORG = 'Default';

    public const SUPERADMIN_ORG = 'Superadmin';

    public const DEFAULT_GNN_COOCCURRENCE = 20;

    public const DEFAULT_GNN_WINDOW = 20;

    public const DEFAULT_HEATMAP_WIDTH = 900;
}
