<?php
/**
 * ============================================================
 * MOCK DATA: DEVELOPMENT VERSION ONLY
 * ============================================================
 * Every record below is fictional sample data used to make the
 * interface testable before the MySQL database exists.
 *
 * - Nothing here is persistent. Forms do not write to this file.
 * - Office names, fund sources and amounts are placeholders and
 *   must be replaced with the MPDO's confirmed lists.
 * - In the database phase these functions will be replaced by
 *   prepared-statement queries; pages only call the functions,
 *   so page code will not need to be rewritten.
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/permissions.php';

/** MOCK: implementing offices (code => name). */
function mockOffices(): array
{
    return [
        'MEO'    => 'Municipal Engineering Office',
        'MHO'    => 'Municipal Health Office',
        'MSWDO'  => 'Municipal Social Welfare and Development Office',
        'MAO'    => 'Municipal Agriculture Office',
        'MDRRMO' => 'Municipal Disaster Risk Reduction and Management Office',
        'MENRO'  => 'Municipal Environment and Natural Resources Office',
        'MPDO'   => 'Municipal Planning and Development Office',
    ];
}

/**
 * MOCK: starting fund sources with their annual ceiling for the AIP year.
 * Copied into the session store on first use (see includes/funds.php).
 */
function mockFundSources(): array
{
    return [
        'DF'     => ['name' => '20% Development Fund',   'code' => 'DF-20',   'category' => 'General Fund',         'ceiling' => 35000000, 'active' => true,  'description' => '20% of the IRA/NTA share set aside for development projects.'],
        'GF'     => ['name' => 'General Fund',           'code' => 'GF-101',  'category' => 'General Fund',         'ceiling' => 30000000, 'active' => true,  'description' => 'Regular appropriations of the municipal general fund.'],
        'LDRRMF' => ['name' => 'Local DRRM Fund',        'code' => 'LDRRMF',  'category' => 'Special Purpose Fund', 'ceiling' => 12000000, 'active' => true,  'description' => 'Local disaster risk reduction and management fund.'],
        'SEF'    => ['name' => 'Special Education Fund', 'code' => 'SEF-201', 'category' => 'Special Purpose Fund', 'ceiling' => 8000000,  'active' => true,  'description' => 'Proceeds of the additional real property tax for education.'],
        'TF'     => ['name' => 'Trust Fund',             'code' => 'TF-301',  'category' => 'Trust Fund',           'ceiling' => 6000000,  'active' => true,  'description' => 'Funds held in trust for specific purposes.'],
        'LP'     => ['name' => 'Loan Proceeds',          'code' => 'LP-401',  'category' => 'External Source',      'ceiling' => 0,        'active' => false, 'description' => 'Proceeds of loans from government financial institutions (not used this year).'],
    ];
}

/** MOCK: PPA types allowed in the encoding form. */
function mockPpaTypes(): array
{
    return ['Program', 'Project', 'Activity'];
}

/**
 * MOCK: Programs, Projects and Activities.
 *
 * status: for_review, returned, recommended, approved, disapproved, ongoing, completed
 */
function mockPpas(): array
{
    $fy = AIP_FISCAL_YEAR;
    return [
        ['ref' => "PPA-$fy-001", 'type' => 'Project',  'title' => 'Concreting of Barangay Farm-to-Market Road (1.2 km)', 'program' => 'Rural Infrastructure Program', 'office' => 'MEO',    'officer' => 'Engr. R. Dela Cruz', 'fund' => 'DF',     'cost' => 14500000, 'start' => "$fy-02-01", 'end' => "$fy-07-31", 'status' => 'for_review',  'updated' => '2026-10-21', 'progress' => 0,   'utilized' => 0,       'record' => '', 'recordDate' => ''],
        ['ref' => "PPA-$fy-002", 'type' => 'Project',  'title' => 'Rehabilitation of Municipal Health Center',            'program' => 'Health Services Enhancement',  'office' => 'MHO',    'officer' => 'Dr. A. Santos',      'fund' => 'DF',     'cost' => 6800000,  'start' => "$fy-03-01", 'end' => "$fy-09-30", 'status' => 'for_review',  'updated' => '2026-10-20', 'progress' => 0,   'utilized' => 0,       'record' => '', 'recordDate' => ''],
        ['ref' => "PPA-$fy-003", 'type' => 'Activity', 'title' => 'Community-Based Disaster Preparedness Training',       'program' => 'Disaster Risk Reduction Program','office' => 'MDRRMO', 'officer' => 'M. Reyes',           'fund' => 'LDRRMF', 'cost' => 950000,   'start' => "$fy-01-15", 'end' => "$fy-06-30", 'status' => 'for_review',  'updated' => '2026-10-19', 'progress' => 0,   'utilized' => 0,       'record' => '', 'recordDate' => ''],
        ['ref' => "PPA-$fy-004", 'type' => 'Program',  'title' => 'Supplementary Feeding Program for Day Care Children',  'program' => 'Social Protection Program',    'office' => 'MSWDO',  'officer' => 'L. Garcia',          'fund' => 'GF',     'cost' => 1800000,  'start' => "$fy-01-02", 'end' => "$fy-12-15", 'status' => 'for_review',  'updated' => '2026-10-18', 'progress' => 0,   'utilized' => 0,       'record' => '', 'recordDate' => ''],
        ['ref' => "PPA-$fy-005", 'type' => 'Project',  'title' => 'Construction of Materials Recovery Facility',          'program' => 'Solid Waste Management Program','office' => 'MENRO',  'officer' => 'J. Ramos',           'fund' => 'DF',     'cost' => 4200000,  'start' => "$fy-04-01", 'end' => "$fy-10-31", 'status' => 'returned',    'updated' => '2026-10-16', 'progress' => 0,   'utilized' => 0,       'record' => '', 'recordDate' => ''],
        ['ref' => "PPA-$fy-006", 'type' => 'Project',  'title' => 'Installation of Solar Street Lights along Poblacion',  'program' => 'Rural Infrastructure Program', 'office' => 'MEO',    'officer' => 'Engr. R. Dela Cruz', 'fund' => 'DF',     'cost' => 3500000,  'start' => "$fy-02-15", 'end' => "$fy-05-31", 'status' => 'recommended', 'updated' => '2026-10-15', 'progress' => 0,   'utilized' => 0,       'record' => '', 'recordDate' => ''],
        ['ref' => "PPA-$fy-007", 'type' => 'Activity', 'title' => 'Distribution of Certified Rice and Vegetable Seeds',   'program' => 'Agricultural Productivity Program','office' => 'MAO',  'officer' => 'P. Mendoza',         'fund' => 'GF',     'cost' => 1250000,  'start' => "$fy-05-01", 'end' => "$fy-06-30", 'status' => 'recommended', 'updated' => '2026-10-14', 'progress' => 0,   'utilized' => 0,       'record' => '', 'recordDate' => ''],
        ['ref' => "PPA-$fy-008", 'type' => 'Project',  'title' => 'Procurement of Rescue Vehicle and Equipment',          'program' => 'Disaster Risk Reduction Program','office' => 'MDRRMO', 'officer' => 'M. Reyes',           'fund' => 'LDRRMF', 'cost' => 5600000,  'start' => "$fy-03-01", 'end' => "$fy-06-30", 'status' => 'recommended', 'updated' => '2026-10-13', 'progress' => 0,   'utilized' => 0,       'record' => '', 'recordDate' => ''],
        ['ref' => "PPA-$fy-009", 'type' => 'Project',  'title' => 'Repair of Public Elementary School Classrooms',        'program' => 'Basic Education Support',      'office' => 'MEO',    'officer' => 'Engr. R. Dela Cruz', 'fund' => 'SEF',    'cost' => 3900000,  'start' => "$fy-04-15", 'end' => "$fy-07-31", 'status' => 'disapproved', 'updated' => '2026-10-10', 'progress' => 0,   'utilized' => 0,       'record' => '', 'recordDate' => ''],
        ['ref' => "PPA-$fy-010", 'type' => 'Program',  'title' => 'Barangay Health Workers Capability Building',          'program' => 'Health Services Enhancement',  'office' => 'MHO',    'officer' => 'Dr. A. Santos',      'fund' => 'GF',     'cost' => 750000,   'start' => "$fy-02-01", 'end' => "$fy-11-30", 'status' => 'approved',    'updated' => '2026-10-08', 'progress' => 0,   'utilized' => 0,       'record' => 'SB Res. No. 2026-114', 'recordDate' => '2026-10-06'],
        ['ref' => "PPA-$fy-011", 'type' => 'Project',  'title' => 'Construction of Drainage System, Barangay Center',     'program' => 'Rural Infrastructure Program', 'office' => 'MEO',    'officer' => 'Engr. R. Dela Cruz', 'fund' => 'DF',     'cost' => 8200000,  'start' => "$fy-01-15", 'end' => "$fy-08-31", 'status' => 'approved',    'updated' => '2026-10-07', 'progress' => 0,   'utilized' => 0,       'record' => '', 'recordDate' => ''],
        ['ref' => "PPA-$fy-012", 'type' => 'Activity', 'title' => 'Tree Planting and Watershed Rehabilitation',           'program' => 'Environmental Protection Program','office' => 'MENRO', 'officer' => 'J. Ramos',          'fund' => 'TF',     'cost' => 600000,   'start' => "$fy-06-01", 'end' => "$fy-09-30", 'status' => 'ongoing',     'updated' => '2026-10-05', 'progress' => 45,  'utilized' => 270000,  'record' => 'SB Res. No. 2026-098', 'recordDate' => '2026-09-14'],
        ['ref' => "PPA-$fy-013", 'type' => 'Program',  'title' => 'Assistance to Individuals in Crisis Situations',        'program' => 'Social Protection Program',    'office' => 'MSWDO',  'officer' => 'L. Garcia',          'fund' => 'GF',     'cost' => 2500000,  'start' => "$fy-01-02", 'end' => "$fy-12-31", 'status' => 'ongoing',     'updated' => '2026-10-03', 'progress' => 60,  'utilized' => 1500000, 'record' => 'SB Res. No. 2026-098', 'recordDate' => '2026-09-14'],
        ['ref' => "PPA-$fy-014", 'type' => 'Activity', 'title' => 'Updating of the Comprehensive Land Use Plan',          'program' => 'Development Planning Support', 'office' => 'MPDO',   'officer' => 'MPDC',               'fund' => 'GF',     'cost' => 1200000,  'start' => "$fy-01-02", 'end' => "$fy-06-30", 'status' => 'completed',   'updated' => '2026-09-30', 'progress' => 100, 'utilized' => 1180000, 'record' => 'SB Res. No. 2026-075', 'recordDate' => '2026-08-20'],
    ];
}

/**
 * MOCK: starting user accounts. Copied into the session store on first use
 * (see includes/store.php). DEV-ONLY: every account uses the development
 * password below; it is hashed when the store is created.
 */
const MOCK_DEV_PASSWORD = 'Dev@2026';

function mockUsers(): array
{
    return [
        ['id' => 1, 'username' => 'admin',     'name' => 'Maria Villanueva', 'email' => 'm.villanueva@indang.example', 'role' => 'Administrator', 'office' => 'MPDO',  'position' => 'System Administrator',                         'status' => 'Active'],
        ['id' => 2, 'username' => 'jbautista', 'name' => 'Jose Bautista',    'email' => 'j.bautista@indang.example',   'role' => 'Reviewer',      'office' => 'MPDO',  'position' => 'Planning Officer II',                          'status' => 'Active'],
        ['id' => 3, 'username' => 'alim',      'name' => 'Ana Lim',          'email' => 'a.lim@indang.example',        'role' => 'Approver',      'office' => 'MPDO',  'position' => 'Municipal Planning and Development Coordinator', 'status' => 'Active'],
        ['id' => 4, 'username' => 'rdelacruz', 'name' => 'Rafael Dela Cruz', 'email' => 'r.delacruz@indang.example',   'role' => 'Encoder',       'office' => 'MEO',   'position' => 'Engineer II',                                  'status' => 'Active'],
        ['id' => 5, 'username' => 'lgarcia',   'name' => 'Liza Garcia',      'email' => 'l.garcia@indang.example',     'role' => 'Encoder',       'office' => 'MSWDO', 'position' => 'Social Welfare Officer I',                     'status' => 'Active'],
        ['id' => 6, 'username' => 'pmendoza',  'name' => 'Paolo Mendoza',    'email' => 'p.mendoza@indang.example',    'role' => 'Encoder',       'office' => 'MAO',   'position' => 'Agricultural Technologist',                    'status' => 'Inactive'],
        ['id' => 7, 'username' => 'cramos',    'name' => 'Carmen Ramos',     'email' => 'c.ramos@indang.example',      'role' => 'Encoder',       'office' => 'MENRO', 'position' => 'Environmental Management Specialist',          'status' => 'Active'],
    ];
}

/** Roles offered in the user form. */
function mockRoles(): array
{
    return array_keys(ROLES);
}

/** Find a single PPA by reference, or null. */
function findPpa(string $ref): ?array
{
    foreach (mockPpas() as $ppa) {
        if ($ppa['ref'] === $ref) {
            return $ppa;
        }
    }
    return null;
}

/** Office name for a code. */
function officeName(string $code): string
{
    return mockOffices()[$code] ?? $code;
}

/** Fund source name for a code. */
function fundName(string $code): string
{
    return fundSources()[$code]['name'] ?? $code;
}
