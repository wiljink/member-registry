<?php

return [

    /*
    |--------------------------------------------------------------------------
    | As-of date
    |--------------------------------------------------------------------------
    | Drives the AGE column and the "AS OF DATE ... BALANCE" headers in the
    | exported registry. Null = use today's date at export time.
    */
    'as_of_date' => env('REGISTRY_AS_OF_DATE'),

    /*
    |--------------------------------------------------------------------------
    | Dropdown option lists (edit here to change what staff can pick)
    |--------------------------------------------------------------------------
    | Each list is [stored value => label shown in the form]. Stored value is
    | what lands in the database and the exported registry.
    */
    'options' => [

        'membership_type' => [
            'Regular'   => 'Regular',
            'Associate' => 'Associate',
        ],

        'membership_kind' => [
            'Full-fledged'     => 'Full-fledged',
            'Non Full-fledged' => 'Non Full-fledged',
        ],

        'migs_status' => [
            'MIGS'     => 'MIGS',
            'Non-MIGS' => 'Non-MIGS',
        ],

        'activity_status' => [
            'Active'   => 'Active',
            'Inactive' => 'Inactive',
        ],

        'civil_status' => [
            'Single'             => 'Single',
            'Married'            => 'Married',
            'Widowed'            => 'Widowed',
            'Legally Separated'  => 'Legally Separated',
            'Annulled'           => 'Annulled',
            'Cohabiting'         => 'Cohabiting (live-in)',
        ],

        'sex' => [
            'Male'      => 'Male',
            'Female'    => 'Female',
            'Intersex'  => 'Intersex',
        ],

        'gender_identity' => [
            'Male'               => 'Male',
            'Female'             => 'Female',
            'LGBTQIA++'          => 'LGBTQIA++',
            'Prefer not to say'  => 'Prefer not to say',
        ],

        'education_attainment' => [
            'No Formal Education'    => 'No Formal Education',
            'Elementary'            => 'Elementary',
            'Elementary Graduate'   => 'Elementary Graduate',
            'High School'           => 'High School',
            'High School Graduate'  => 'High School Graduate',
            'Senior High School'    => 'Senior High School',
            'Vocational'            => 'Vocational / Technical',
            'College Level'         => 'College Level',
            'College Graduate'      => 'College Graduate',
            'Postgraduate'          => 'Postgraduate',
        ],

        'occupation_category' => [
            'Government'      => 'Government',
            'Private'        => 'Private',
            'Self-employed'  => 'Self-employed',
            'Unemployed'     => 'Unemployed',
        ],

        'occupation_status' => [
            'Permanent'   => 'Permanent',
            'Contractual' => 'Contractual',
            'Casual'      => 'Casual',
            'Seasonal'    => 'Seasonal',
            'Retired'     => 'Retired',
        ],

        'industry' => [
            'Agriculture'                 => 'Agriculture, Forestry and Fishing',
            'Mining'                      => 'Mining and Quarrying',
            'Manufacturing'               => 'Manufacturing',
            'Construction'                => 'Construction',
            'Wholesale and Retail Trade'  => 'Wholesale and Retail Trade',
            'Transportation'              => 'Transportation and Storage',
            'Accommodation and Food'      => 'Accommodation and Food Service',
            'Information and Communication' => 'Information and Communication',
            'Financial and Insurance'     => 'Financial and Insurance',
            'Real Estate'                 => 'Real Estate',
            'Professional Services'       => 'Professional, Scientific and Technical',
            'Administrative Services'     => 'Administrative and Support Services',
            'Public Administration'       => 'Public Administration and Defense',
            'Education'                   => 'Education',
            'Health'                      => 'Human Health and Social Work',
            'Other Services'              => 'Other Service Activities',
            'Household Employment'        => 'Household / Domestic Work',
        ],

        'religion' => [
            'Roman Catholic'    => 'Roman Catholic',
            'Islam'             => 'Islam',
            'Christian'         => 'Christian (Other)',
            'Iglesia ni Cristo' => 'Iglesia ni Cristo',
            'Others'            => 'Others',
            'No Religion'       => 'No Religion',
            'Prefer not to say' => 'Prefer not to say',
        ],

        'ethnicity' => [
            'Cebuano'    => 'Cebuano',
            'Boholano'   => 'Boholano',
            'Tagalog'    => 'Tagalog',
            'Ilocano'    => 'Ilocano',
            'Bisaya'     => 'Bisaya (Other)',
            'IP'         => 'Indigenous Peoples',
            'Others'     => 'Others',
            'None'       => 'None / Not applicable',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Fields that must be filled for a member to count as "complete"
    |--------------------------------------------------------------------------
    | Used by Member::recomputeCompletion(). Keep this to the columns the CDA
    | registry truly requires and that no source system supplies.
    */
    'required_for_complete' => [
        'tin',
        'date_accepted',
        'membership_type',
        'membership_kind',
        'activity_status',
        'present_address',
        'sex_assigned_at_birth',
        'civil_status',
        'education_attainment',
        'occupation_category',
        'number_of_dependents',
        'religion',
        'is_pwd',
    ],
];
