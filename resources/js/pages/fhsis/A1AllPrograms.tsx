import React, { useState, useEffect, useMemo } from 'react';

// ─── Location Data Shapes ────────────────────────────────────────────────────
interface Region { regCode: string; regDesc: string; }
interface Province { provCode: string; provDesc: string; regCode: string; }
interface Municipality { citymunCode: string; citymunDesc: string; provCode: string; }

interface A1AllProgramsProps {
  regions?: Region[];
  provinces?: Province[];
  municipalities?: Municipality[];
}

// ─── Types ────────────────────────────────────────────────────────────────────
interface FilterState {
  year?: string;
  month?: string;
  region?: string;
  province?: string;
  municipality?: string;
  rhuName?: string;
}

interface ChildCareData {
  schoolBasedImmunization?: { male: number; female: number; total: number };
  nutrition?: { male: number; female: number; total: number };
}

interface NCDData {
  hypertension?: number;
  diabetes?: number;
  smokers?: number;
}

interface InfectiousDiseaseData {
  filariasis?: { examined: number };
  leprosy?: { registered: number };
}

interface FacilityData {
  locationBreakdown?: any[];
}

interface ReportData {
  'A. Child Care'?: ChildCareData;
  'B. NCDs'?: NCDData;
  'G. Infectious Diseases'?: InfectiousDiseaseData;
  'Facility & Workforce'?: FacilityData;
  summary?: {
    year: string;
    month: string;
    province: string;
    rhuName: string;
    projectedPopulation: number;
  };
}

// ─── Reusable cell helpers ────────────────────────────────────────────────────
const Th = ({
  children,
  className = '',
  rowSpan,
  colSpan,
}: {
  children?: React.ReactNode;
  className?: string;
  rowSpan?: number;
  colSpan?: number;
}) => (
  <th
    rowSpan={rowSpan}
    colSpan={colSpan}
    className={`border border-gray-400 px-2 py-1 text-center text-xs font-semibold bg-gray-200 ${className}`}
  >
    {children}
  </th>
);

const Td = ({
  children,
  className = '',
  colSpan,
  rowSpan,
}: {
  children?: React.ReactNode;
  className?: string;
  colSpan?: number;
  rowSpan?: number;
}) => (
  <td
    colSpan={colSpan}
    rowSpan={rowSpan}
    className={`border border-gray-400 px-2 py-1 text-xs ${className}`}
  >
    {children}
  </td>
);

const InputCell = ({ className = '', value = '' }: { className?: string; value?: string }) => (
  <td className={`border border-gray-400 px-1 py-0.5 ${className}`}>
    <input
      type="number"
      className="w-full text-center text-xs border-0 outline-none bg-transparent"
      defaultValue={value}
      readOnly
    />
  </td>
);

const SectionHeader = ({
  children,
  colSpan,
}: {
  children: React.ReactNode;
  colSpan: number;
}) => (
  <tr className="bg-blue-700">
    <td
      colSpan={colSpan}
      className="border border-gray-400 px-2 py-1 text-sm font-bold text-white text-center"
    >
      {children}
    </td>
  </tr>
);

const SubSectionHeader = ({
  children,
  colSpan,
}: {
  children: React.ReactNode;
  colSpan: number;
}) => (
  <tr className="bg-blue-100">
    <td
      colSpan={colSpan}
      className="border border-gray-400 px-2 py-1 text-xs font-bold text-blue-900"
    >
      {children}
    </td>
  </tr>
);

// Sex column helpers
const SexHeaders = () => (
  <>
    <Th>Male</Th>
    <Th>Female</Th>
    <Th>Total</Th>
  </>
);
const SexInputs = ({ maleVal = '', femaleVal = '', totalVal = '' }) => (
  <>
    <InputCell value={maleVal} />
    <InputCell value={femaleVal} />
    <InputCell value={totalVal} />
  </>
);

// ─── FILTER PANEL COMPONENT ───────────────────────────────────────────────────
interface FilterPanelProps {
  filters: FilterState;
  onFilterChange: (filters: FilterState) => void;
  onApplyFilter: () => void;
  isLoading?: boolean;
  regions?: Region[];
  provinces?: Province[];
  municipalities?: Municipality[];
}

const FilterPanel: React.FC<FilterPanelProps> = ({
  filters,
  onFilterChange,
  onApplyFilter,
  isLoading = false,
  regions = [],
  provinces = [],
  municipalities = [],
}) => {
  const handleInputChange = (key: keyof FilterState, value: string) => {
    onFilterChange({
      ...filters,
      [key]: value,
    });
  };

  const handleRegionChange = (value: string) => {
    onFilterChange({
      ...filters,
      region: value,
      province: '',
      municipality: '',
    });
  };

  const handleProvinceChange = (value: string) => {
    onFilterChange({
      ...filters,
      province: value,
      municipality: '',
    });
  };

  const filteredProvinces = useMemo(
    () => provinces.filter((p) => p.regCode === filters.region),
    [filters.region, provinces],
  );
  const filteredMunicipalities = useMemo(
    () => municipalities.filter((m) => m.provCode === filters.province),
    [filters.province, municipalities],
  );

  return (
    <div className="mb-4 p-4 bg-gray-50 border border-gray-300 rounded-lg">
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        {/* Year */}
        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">Year</label>
          <input
            type="number"
            value={filters.year || ''}
            onChange={(e) => handleInputChange('year', e.target.value)}
            placeholder="YYYY"
            className="w-full px-2 py-1 text-xs border border-gray-300 rounded outline-none focus:border-blue-500"
          />
        </div>

        {/* Month */}
        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">Month</label>
          <select
            value={filters.month || ''}
            onChange={(e) => handleInputChange('month', e.target.value)}
            className="w-full px-2 py-1 text-xs border border-gray-300 rounded outline-none focus:border-blue-500"
          >
            <option value="">All</option>
            <option value="01">January</option>
            <option value="02">February</option>
            <option value="03">March</option>
            <option value="04">April</option>
            <option value="05">May</option>
            <option value="06">June</option>
            <option value="07">July</option>
            <option value="08">August</option>
            <option value="09">September</option>
            <option value="10">October</option>
            <option value="11">November</option>
            <option value="12">December</option>
          </select>
        </div>

        {/* Region */}
        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">Region</label>
          <select
            value={filters.region || ''}
            onChange={(e) => handleRegionChange(e.target.value)}
            className="w-full px-2 py-1 text-xs border border-gray-300 rounded outline-none focus:border-blue-500"
          >
            <option value="">Select Region</option>
            {regions.map((r) => (
              <option key={r.regCode} value={r.regCode}>{r.regDesc}</option>
            ))}
          </select>
        </div>

        {/* Province */}
        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">Province</label>
          <select
            value={filters.province || ''}
            disabled={!filters.region}
            onChange={(e) => handleProvinceChange(e.target.value)}
            className="w-full px-2 py-1 text-xs border border-gray-300 rounded outline-none focus:border-blue-500 disabled:bg-gray-100"
          >
            <option value="">Select Province</option>
            {filteredProvinces.map((p) => (
              <option key={p.provCode} value={p.provCode}>{p.provDesc}</option>
            ))}
          </select>
        </div>

        {/* Municipality */}
        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">Municipality</label>
          <select
            value={filters.municipality || ''}
            disabled={!filters.province}
            onChange={(e) => handleInputChange('municipality', e.target.value)}
            className="w-full px-2 py-1 text-xs border border-gray-300 rounded outline-none focus:border-blue-500 disabled:bg-gray-100"
          >
            <option value="">Select Municipality</option>
            {filteredMunicipalities.map((m) => (
              <option key={m.citymunCode} value={m.citymunCode}>{m.citymunDesc}</option>
            ))}
          </select>
        </div>

        {/* RHU Name */}
        <div>
          <label className="block text-xs font-semibold text-gray-700 mb-1">RHU Name</label>
          <input
            type="text"
            value={filters.rhuName || ''}
            onChange={(e) => handleInputChange('rhuName', e.target.value)}
            placeholder="RHU Name"
            className="w-full px-2 py-1 text-xs border border-gray-300 rounded outline-none focus:border-blue-500"
          />
        </div>
      </div>

      <div className="mt-3 flex gap-2 justify-end">
        <button
          onClick={() => onFilterChange({ year: '', month: '', region: '', province: '', municipality: '', rhuName: '' })}
          className="px-4 py-2 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-100 transition"
        >
          Clear
        </button>
        <button
          onClick={onApplyFilter}
          disabled={isLoading}
          className="px-4 py-2 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700 transition disabled:bg-blue-400"
        >
          {isLoading ? 'Filtering...' : 'Apply Filter'}
        </button>
      </div>
    </div>
  );
};

// ─── HEADER INFO ─────────────────────────────────────────────────────────────
const FormHeader = ({ data }: { data?: ReportData }) => (
  <div className="mb-4 grid grid-cols-2 gap-4 text-sm">
    <div className="space-y-1">
      <div>
        FHSIS REPORT for the:{' '}
        <span className="border-b border-gray-500 inline-block w-28">
          {data?.summary?.month || '&nbsp;'}
        </span>{' '}
        Year:{' '}
        <span className="border-b border-gray-500 inline-block w-20">
          {data?.summary?.year || '&nbsp;'}
        </span>
      </div>
      <div>
        Name of RHU:{' '}
        <span className="border-b border-gray-500 inline-block w-64">
          {data?.summary?.rhuName || '&nbsp;'}
        </span>
      </div>
    </div>
    <div className="space-y-1">
      <div>
        Name of Province:{' '}
        <span className="border-b border-gray-500 inline-block w-52">
          {data?.summary?.province || '&nbsp;'}
        </span>
      </div>
      <div>
        Projected Population of the Year:{' '}
        <span className="border-b border-gray-500 inline-block w-32">
          {data?.summary?.projectedPopulation || '&nbsp;'}
        </span>
      </div>
    </div>
  </div>
);

// ─── SECTION A: Child Care and Services ──────────────────────────────────────
const sbiLeft = [
  '1. Grade 1 learners given Td',
  '2. Grade 1 learners given MR',
  '3. Grade 7 learners given Td',
  '4. Grade 7 learners given MR',
  '5. HPV 1 (SBI)',
];
const sbiRight = [
  '6. HPV 1 (CBI)',
  '7. HPV 2 (CBI)',
  '8. Number of Grade 1 enrolled learners',
  '9. Number of Grade 4 enrolled learners',
  '10. Number of Grade 7 enrolled learners',
];

const nutritionLeft = [
  '6. Children 0–59 months old SEEN during the reporting period at health facilities',
  '6a. Identified MAM',
  '6b. Identified SAM',
  '7. MAM enrolled to SFP',
  '7a. Cured',
  '7b. Non-cured',
  '7c. Defaulted',
];
const nutritionRight = [
  '7d. Died',
  '8. SAM without complication admitted to OTC',
  '8a. Cured',
  '8b. Non-cured',
  '8c. Defaulted',
  '8d. Died',
  '',
];

const SectionA = ({ data }: { data?: ReportData }) => {
  const maxSBI = Math.max(sbiLeft.length, sbiRight.length);
  const maxNutrition = Math.max(nutritionLeft.length, nutritionRight.length);

  const sbi = data?.['A. Child Care']?.schoolBasedImmunization;
  const nutrition = data?.['A. Child Care']?.nutrition;

  return (
    <div className="mb-6">
      <table className="w-full border-collapse text-xs">
        <tbody>
          <SectionHeader colSpan={10}>SECTION A. CHILD CARE AND SERVICES</SectionHeader>

          {/* ── A. School and Community-Based Immunization ── */}
          <SubSectionHeader colSpan={10}>A. School and Community-Based Immunization</SubSectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">Indicators</Th>
            <SexHeaders />
            <Th>Remarks</Th>
            <Th className="text-left w-5/12">Indicators</Th>
            <SexHeaders />
            <Th>Remarks</Th>
          </tr>
          {Array.from({ length: maxSBI }).map((_, i) => {
            const l = sbiLeft[i] ?? '';
            const r = sbiRight[i] ?? '';
            // Row 0 (Grade 1 / Td) carries the aggregate SBI totals from the API
            const lMale   = i === 0 ? String(sbi?.male   ?? '') : '';
            const lFemale = i === 0 ? String(sbi?.female ?? '') : '';
            const lTotal  = i === 0 ? String(sbi?.total  ?? '') : '';
            return (
              <tr key={i}>
                <Td className="pl-4 w-5/12">{l}</Td>
                {l ? (
                  <><SexInputs maleVal={lMale} femaleVal={lFemale} totalVal={lTotal} /><InputCell /></>
                ) : (
                  <td colSpan={4} className="border border-gray-400" />
                )}
                <Td className="pl-4 w-5/12">{r}</Td>
                {r ? (
                  <><SexInputs /><InputCell /></>
                ) : (
                  <td colSpan={4} className="border border-gray-400" />
                )}
              </tr>
            );
          })}

          {/* ── B. Nutrition ── */}
          <SubSectionHeader colSpan={10}>B. Nutrition</SubSectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">Indicators</Th>
            <SexHeaders />
            <Th>Remarks</Th>
            <Th className="text-left w-5/12">Indicators</Th>
            <SexHeaders />
            <Th>Remarks</Th>
          </tr>
          {Array.from({ length: maxNutrition }).map((_, i) => {
            const l = nutritionLeft[i] ?? '';
            const r = nutritionRight[i] ?? '';
            const lIndent = l.startsWith('6a') || l.startsWith('6b') || l.startsWith('7a') || l.startsWith('7b') || l.startsWith('7c') ? 'pl-8' : 'pl-4';
            const rIndent = r.startsWith('7d') || r.startsWith('8a') || r.startsWith('8b') || r.startsWith('8c') || r.startsWith('8d') ? 'pl-8' : 'pl-4';
            // Row 0 ("Children 0-59 months SEEN") carries the aggregate nutrition totals from the API
            const lMale   = i === 0 ? String(nutrition?.male   ?? '') : '';
            const lFemale = i === 0 ? String(nutrition?.female ?? '') : '';
            const lTotal  = i === 0 ? String(nutrition?.total  ?? '') : '';
            return (
              <tr key={i}>
                <Td className={`${lIndent} w-5/12`}>{l}</Td>
                {l ? (
                  <><SexInputs maleVal={lMale} femaleVal={lFemale} totalVal={lTotal} /><InputCell /></>
                ) : (
                  <td colSpan={4} className="border border-gray-400" />
                )}
                <Td className={`${rIndent} w-5/12`}>{r}</Td>
                {r ? (
                  <><SexInputs /><InputCell /></>
                ) : (
                  <td colSpan={4} className="border border-gray-400" />
                )}
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
};

// ─── SECTION B: Non-Communicable Diseases ────────────────────────────────────
const SectionB = ({ data }: { data?: ReportData }) => {
  const ncd = data?.['B. NCDs'];
  const rows: [string, string][] = [
    ['1. Hypertension cases identified', String(ncd?.hypertension ?? '')],
    ['2. Diabetes cases identified',     String(ncd?.diabetes     ?? '')],
    ['3. Other NCD cases identified',    ''],
  ];

  return (
    <div className="mb-6">
      <table className="w-full border-collapse text-xs">
        <tbody>
          <SectionHeader colSpan={5}>SECTION B. NON-COMMUNICABLE DISEASES</SectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left">Indicators</Th>
            <SexHeaders />
            <Th>Remarks</Th>
          </tr>
          {rows.map(([label, total], i) => (
            <tr key={i}>
              <Td className="pl-4">{label}</Td>
              <SexInputs totalVal={total} />
              <InputCell />
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

// ─── SECTION G: Infectious Diseases ──────────────────────────────────────────
const filarLeft = [
  '1. No. of individual examined for lymphatic filariasis',
  '1a. Nocturnal Blood Examination (NBE)',
  '1b. Rapid Diagnostic Test (RDT)',
  '1c. Total no. of individuals examined for lymphedema through NBE and RDT',
  '2. No. of individual found positive for lymphatic filariasis',
  '2a. Nocturnal Blood Examination (NBE)',
  '2b. Rapid Diagnostic Test (RDT)',
  '2c. Total no. of individuals found positive for lymphedema through NBE and RDT',
  '3. Lymphedema',
  '3a. 2-4 years old',
  '3b. 5-14 years old',
  '3c. 15 years old and above',
  '3d. Total no. of individuals aged 2 yrs old and above examined for the 1st time with lymphedema',
  '4. Elephentiasis',
  '4a. 2-4 years old',
  '4b. 5-14 years old',
  '4c. 15 years old and above',
  '4d. Total no. of individuals aged 2 yrs old and above examined for the 1st time with Elephentiasis',
];

const filarRight = [
  '3. Hydrocele',
  '3a. 2-4 years old',
  '3b. 5-14 years old',
  '3c. 15 years old and above',
  '3d. Total no. of individuals aged 2 yrs old and above examined for the 1st time with Hydrocele',
  '4. Number of individuals who received Mass Drug Administration',
  '4a. 2-4 years old',
  '4b. 5-14 years old',
  '4c. 15 years old and above',
  '4d. Total no. of individuals aged 2 yrs old and above who received MDA',
  '', '', '', '', '', '', '', '',
];

const leprosyLeft = [
  '1. No. of registered Leprosy cases',
  '1a. 0-14 years old',
  '1b. 15-18 years old',
  '1c. 19 years old and above',
  '2. No. of newly detected case',
  '2a. 0-14 years old',
  '2b. 15-18 years old',
  '2c. 19 years old and above',
  '3. Confirmed Leprosy Cases',
  '3a. 0-14 years old',
  '3b. 15-18 years old',
  '3c. 19 years old and above',
];

const leprosyRight = [
  '4. Completed fixed duration Multi-Drug Therapy (MDT)',
  '4a. 0-14 years old',
  '4b. 15-18 years old',
  '4c. 19 years old and above',
  '5. No. of confirmed leprosy cases treated',
  '5a. 0-14 years old',
  '5b. 15-18 years old',
  '5c. 19 years old and above',
  '6. Newly Detected Cases with Grade 2 Disabilities',
  '6a. 0-14 years old',
  '6b. 15-18 years old',
  '6c. 19 years old and above',
];

const SectionG = ({ data }: { data?: ReportData }) => {
  const maxFilar = Math.max(filarLeft.length, filarRight.length);
  const maxLeprosy = Math.max(leprosyLeft.length, leprosyRight.length);

  const infectious = data?.['G. Infectious Diseases'];
  const filarExamined  = String(infectious?.filariasis?.examined  ?? '');
  const leprosyRegistered = String(infectious?.leprosy?.registered ?? '');

  const isSubItem = (s: string) =>
    /^\d+[a-d]\./.test(s) || /^[1-4][a-d]\./.test(s);

  return (
    <div className="mb-6">
      <table className="w-full border-collapse text-xs">
        <tbody>
          <SectionHeader colSpan={10}>
            SECTION G. INFECTIOUS DISEASE PREVENTION AND CONTROL SERVICES
          </SectionHeader>

          {/* ── A. Filariasis ── */}
          <SubSectionHeader colSpan={10}>A. Filariasis</SubSectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">Indicators</Th>
            <SexHeaders />
            <Th>Remarks</Th>
            <Th className="text-left w-5/12">Indicators</Th>
            <SexHeaders />
            <Th>Remarks</Th>
          </tr>
          {Array.from({ length: maxFilar }).map((_, i) => {
            const l = filarLeft[i] ?? '';
            const r = filarRight[i] ?? '';
            // Row 0 ("No. of individual examined") carries the filariasis total from the API
            const lTotal = i === 0 ? filarExamined : '';
            return (
              <tr key={i}>
                <Td className={`${isSubItem(l) ? 'pl-8' : 'pl-4'} w-5/12`}>{l}</Td>
                {l ? (
                  <><SexInputs totalVal={lTotal} /><InputCell /></>
                ) : (
                  <td colSpan={4} className="border border-gray-400" />
                )}
                <Td className={`${isSubItem(r) ? 'pl-8' : 'pl-4'} w-5/12`}>{r}</Td>
                {r ? (
                  <><SexInputs /><InputCell /></>
                ) : (
                  <td colSpan={4} className="border border-gray-400" />
                )}
              </tr>
            );
          })}

          {/* ── E. Leprosy ── */}
          <SubSectionHeader colSpan={10}>E. Leprosy</SubSectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">Indicators</Th>
            <SexHeaders />
            <Th>Remarks</Th>
            <Th className="text-left w-5/12">Indicators</Th>
            <SexHeaders />
            <Th>Remarks</Th>
          </tr>
          {Array.from({ length: maxLeprosy }).map((_, i) => {
            const l = leprosyLeft[i] ?? '';
            const r = leprosyRight[i] ?? '';
            // Row 0 ("No. of registered Leprosy cases") carries the leprosy total from the API
            const lTotal = i === 0 ? leprosyRegistered : '';
            return (
              <tr key={i}>
                <Td className={`${isSubItem(l) ? 'pl-8' : 'pl-4'} w-5/12`}>{l}</Td>
                {l ? (
                  <><SexInputs totalVal={lTotal} /><InputCell /></>
                ) : (
                  <td colSpan={4} className="border border-gray-400" />
                )}
                <Td className={`${isSubItem(r) ? 'pl-8' : 'pl-4'} w-5/12`}>{r}</Td>
                {r ? (
                  <><SexInputs /><InputCell /></>
                ) : (
                  <td colSpan={4} className="border border-gray-400" />
                )}
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
};

// ─── SECTION: Health Facility & Workforce Data ───────────────────────────────
const SectionFacility = ({ data }: { data?: ReportData }) => {
  const locationBreakdown = data?.['Facility & Workforce']?.locationBreakdown ?? [];

  // Derive counts from locationBreakdown
  const totalBarangays  = locationBreakdown.length;
  const totalHouseholds = locationBreakdown.reduce(
    (sum: number, row: any) => sum + (row.total_households ?? 0), 0
  );

  const facilityRows: [string, boolean, string][] = [
    ['1. No. of Barangays - Total',         false, String(totalBarangays  || '')],
    ['2. No. of Households - (Projected)',   false, String(totalHouseholds || '')],
    ['3. No. of Health Centers - Total',     false, ''],
    ['a. Main Health Centers - Total',       true,  ''],
    ['b. City Health Centers - Total',       true,  ''],
    ['c. Rural Health Units - Total',        true,  ''],
    ['d. Super Health Centers - Total',      true,  ''],
    ['4. No. of Barangay Health Stations - Total', false, ''],
    ['5. No. of Health Workers - Total',     false, ''],
    ['a. Physicians/Doctors - Total',        true,  ''],
    ['b. Dentists - Total',                  true,  ''],
    ['c. Nurses - Total',                    true,  ''],
    ['d. Midwives - Total',                  true,  ''],
    ['e. Medical Technologists - Total',     true,  ''],
    ['f. Nutritionists/Dietitians - Total',  true,  ''],
    ['g. Sanitary Engineers - Total',        true,  ''],
    ['h. Sanitary Inspectors - Total',       true,  ''],
    ['i. Active BHWs - Total',               true,  ''],
  ];

  return (
    <div className="mb-6">
      <table className="w-full border-collapse text-xs">
        <tbody>
          <SectionHeader colSpan={5}>HEALTH FACILITY AND WORKFORCE DATA</SectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left">Indicators</Th>
            <SexHeaders />
            <Th>Remarks</Th>
          </tr>
          {facilityRows.map(([label, sub, total], i) => (
            <tr key={i}>
              <Td className={sub ? 'pl-8' : 'pl-4'}>{label}</Td>
              <SexInputs totalVal={total} />
              <InputCell />
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

// ─── MAIN COMPONENT ───────────────────────────────────────────────────────────
export default function A1AllPrograms({
  regions = [],
  provinces = [],
  municipalities = [],
}: A1AllProgramsProps) {
  const [activeSection, setActiveSection] = useState<string>('all');
  const [filters, setFilters] = useState<FilterState>({});
  const [reportData, setReportData] = useState<ReportData | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const sections = [
    { id: 'all', label: 'All' },
    { id: 'a', label: 'A. Child Care' },
    { id: 'b', label: 'B. NCDs' },
    { id: 'g', label: 'G. Infectious Diseases' },
    { id: 'facility', label: 'Facility & Workforce' },
  ];

  const show = (id: string) => activeSection === 'all' || activeSection === id;

  const handleFilterChange = (newFilters: FilterState) => {
    setFilters(newFilters);
  };

  const handleApplyFilter = async () => {
    setIsLoading(true);
    setError(null);

    try {
      // Build query string from filters
      const queryParams = new URLSearchParams();
      if (filters.year) queryParams.append('year', filters.year);
      if (filters.month) queryParams.append('month', filters.month);
      if (filters.region) queryParams.append('region', filters.region);
      if (filters.province) queryParams.append('province', filters.province);
      if (filters.municipality) queryParams.append('municipality', filters.municipality);
      if (filters.rhuName) queryParams.append('rhu_name', filters.rhuName);

      const response = await fetch(`/qfhsis/public/api/reports/filtered-m1-all?${queryParams.toString()}`);

      if (!response.ok) {
        throw new Error(`API error: ${response.statusText}`);
      }

      const data = await response.json();
      setReportData(data.data || data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch filtered report');
      console.error('Filter error:', err);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="bg-white shadow-sm rounded-lg border border-gray-200 p-4">
      {/* Header */}
      <div className="mb-4 flex flex-wrap justify-between items-center gap-2">
        <div>
          <h2 className="text-xl font-bold text-gray-800">A1: All Programs</h2>
          <p className="text-xs text-gray-500">FHSIS Annual Report Form</p>
        </div>
        <button
          onClick={() => window.print()}
          className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition text-sm"
        >
          Export / Print
        </button>
      </div>

      {/* Filter Panel */}
      <FilterPanel
        filters={filters}
        onFilterChange={handleFilterChange}
        onApplyFilter={handleApplyFilter}
        isLoading={isLoading}
        regions={regions}
        provinces={provinces}
        municipalities={municipalities}
      />

      {/* Error message */}
      {error && (
        <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded text-xs text-red-700">
          {error}
        </div>
      )}

      {/* Section nav */}
      <div className="mb-4 flex flex-wrap gap-1">
        {sections.map((s) => (
          <button
            key={s.id}
            onClick={() => setActiveSection(s.id)}
            className={`px-3 py-1 rounded text-xs font-medium border transition ${
              activeSection === s.id
                ? 'bg-blue-600 text-white border-blue-600'
                : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'
            }`}
          >
            {s.label}
          </button>
        ))}
      </div>

      {/* Form fields */}
      <FormHeader data={reportData} />

      {/* Sections */}
      <div className="overflow-x-auto space-y-2">
        {show('a') && <SectionA data={reportData} />}
        {show('b') && <SectionB data={reportData} />}
        {show('g') && <SectionG data={reportData} />}
        {show('facility') && <SectionFacility data={reportData} />}
      </div>
    </div>
  );
}