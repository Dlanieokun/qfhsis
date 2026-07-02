import React, { useState } from 'react';

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

const InputCell = ({ className = '' }: { className?: string }) => (
  <td className={`border border-gray-400 px-1 py-0.5 ${className}`}>
    <input
      type="number"
      className="w-full text-center text-xs border-0 outline-none bg-transparent"
      defaultValue=""
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
const SexInputs = () => (
  <>
    <InputCell />
    <InputCell />
    <InputCell />
  </>
);

// ─── HEADER INFO ─────────────────────────────────────────────────────────────
const FormHeader = () => (
  <div className="mb-4 grid grid-cols-2 gap-4 text-sm">
    <div className="space-y-1">
      <div>
        FHSIS REPORT for the:{' '}
        <span className="border-b border-gray-500 inline-block w-28">&nbsp;</span>{' '}
        Year:{' '}
        <span className="border-b border-gray-500 inline-block w-20">&nbsp;</span>
      </div>
      <div>
        Name of RHU:{' '}
        <span className="border-b border-gray-500 inline-block w-64">&nbsp;</span>
      </div>
    </div>
    <div className="space-y-1">
      <div>
        Name of Province:{' '}
        <span className="border-b border-gray-500 inline-block w-52">&nbsp;</span>
      </div>
      <div>
        Projected Population of the Year:{' '}
        <span className="border-b border-gray-500 inline-block w-32">&nbsp;</span>
      </div>
    </div>
  </div>
);

// ─── SECTION A: Child Care and Services ──────────────────────────────────────

// School-Based Immunization: left (items 1–5) paired with right (items 6–10)
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

// Nutrition: left (items 6–7c) paired with right (items 7d–8d)
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

const SectionA = () => {
  const maxSBI = Math.max(sbiLeft.length, sbiRight.length);
  const maxNutrition = Math.max(nutritionLeft.length, nutritionRight.length);

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
            return (
              <tr key={i}>
                <Td className="pl-4 w-5/12">{l}</Td>
                {l ? (
                  <><SexInputs /><InputCell /></>
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
            return (
              <tr key={i}>
                <Td className={`${lIndent} w-5/12`}>{l}</Td>
                {l ? (
                  <><SexInputs /><InputCell /></>
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
const SectionB = () => (
  <div className="mb-6">
    <table className="w-full border-collapse text-xs">
      <tbody>
        <SectionHeader colSpan={5}>SECTION B. NON-COMMUNICABLE DISEASES</SectionHeader>

        {/* ── A. Immunization for Senior Citizens ── */}
        <SubSectionHeader colSpan={5}>A. Immunization for Senior Citizens</SubSectionHeader>
        <tr className="bg-gray-100">
          <Th className="text-left">Indicators</Th>
          <SexHeaders />
          <Th>Remarks</Th>
        </tr>
        {[
          '1. Senior Citizens Seen who had not previously received PPV upon reaching 60 years old',
          '2. Senior citizens aged 60 years old and above who received one (1) dose of Pneumococcal Polysaccharide Vaccine',
          '3. Senior Citizens Seen',
          '4. Senior citizens aged 60 years old and above who received one (1) dose of Influenza Vaccine',
        ].map((label, i) => (
          <tr key={i}>
            <Td className="pl-4">{label}</Td>
            <SexInputs />
            <InputCell />
          </tr>
        ))}
      </tbody>
    </table>
  </div>
);

// ─── SECTION G: Infectious Disease Prevention and Control ────────────────────

// Filariasis left column (rows 36–53 left side)
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

// Filariasis right column
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

// Leprosy left column
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

// Leprosy right column
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

const SectionG = () => {
  const maxFilar = Math.max(filarLeft.length, filarRight.length);
  const maxLeprosy = Math.max(leprosyLeft.length, leprosyRight.length);

  // Helper to detect sub-item indentation
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
            return (
              <tr key={i}>
                <Td className={`${isSubItem(l) ? 'pl-8' : 'pl-4'} w-5/12`}>{l}</Td>
                {l ? (
                  <><SexInputs /><InputCell /></>
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
            return (
              <tr key={i}>
                <Td className={`${isSubItem(l) ? 'pl-8' : 'pl-4'} w-5/12`}>{l}</Td>
                {l ? (
                  <><SexInputs /><InputCell /></>
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
const SectionFacility = () => {
  const facilityRows: [string, boolean][] = [
    ['1. No. of Barangays - Total', false],
    ['2. No. of Households - (Projected)', false],
    ['3. No. of Health Centers - Total', false],
    ['a. Main Health Centers - Total', true],
    ['b. City Health Centers - Total', true],
    ['c. Rural Health Units - Total', true],
    ['d. Super Health Centers - Total', true],
    ['4. No. of Barangay Health Stations - Total', false],
    ['5. No. of Health Workers - Total', false],
    ['a. Physicians/Doctors - Total', true],
    ['b. Dentists - Total', true],
    ['c. Nurses - Total', true],
    ['d. Midwives - Total', true],
    ['e. Medical Technologists - Total', true],
    ['f. Nutritionists/Dietitians - Total', true],
    ['g. Sanitary Engineers - Total', true],
    ['h. Sanitary Inspectors - Total', true],
    ['i. Active BHWs - Total', true],
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
          {facilityRows.map(([label, sub], i) => (
            <tr key={i}>
              <Td className={sub ? 'pl-8' : 'pl-4'}>{label}</Td>
              <SexInputs />
              <InputCell />
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

// ─── MAIN COMPONENT ───────────────────────────────────────────────────────────
export default function A1AllPrograms() {
  const [activeSection, setActiveSection] = useState<string>('all');

  const sections = [
    { id: 'all',      label: 'All' },
    { id: 'a',        label: 'A. Child Care' },
    { id: 'b',        label: 'B. NCDs' },
    { id: 'g',        label: 'G. Infectious Diseases' },
    { id: 'facility', label: 'Facility & Workforce' },
  ];

  const show = (id: string) => activeSection === 'all' || activeSection === id;

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
      <FormHeader />

      {/* Sections */}
      <div className="overflow-x-auto space-y-2">
        {show('a')        && <SectionA />}
        {show('b')        && <SectionB />}
        {show('g')        && <SectionG />}
        {show('facility') && <SectionFacility />}
      </div>
    </div>
  );
}
