epa-gov migration steps
=======================

1. Export epa-gov (json, tagging and grouping csv, brief csv)
1. Finding matches between old EPA-GOV data.json data and new WAF/FGDS harvested datasets
1. Swapping urls between found matches from previous step, adding `_epa_deleted` suffix to old data.json matches
1. Re-applying groups and tags, exported on step 1 for consistency
1. Harvesting new EPA-GOV data.json (old epa-gov data.json datasets will be updated/removed)
