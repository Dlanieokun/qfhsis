import React, { useState } from 'react';

// ─── Types ────────────────────────────────────────────────────────────────────

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

const GroupHeader = ({
  children,
  colSpan,
}: {
  children: React.ReactNode;
  colSpan: number;
}) => (
  <tr className="bg-gray-100">
    <td
      colSpan={colSpan}
      className="border border-gray-400 px-2 py-1 text-xs font-semibold"
    >
      {children}
    </td>
  </tr>
);

// Sex columns helpers
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

// ─── HEADER INFO ──────────────────────────────────────────────────────────────
const FormHeader = () => (
  <div className="mb-4 grid grid-cols-2 gap-4 text-sm">
    <div className="space-y-1">
      <div>
        FHSIS REPORT for the Month:{' '}
        <span className="border-b border-gray-500 inline-block w-28">&nbsp;</span> Year:{' '}
        <span className="border-b border-gray-500 inline-block w-20">&nbsp;</span>
      </div>
      <div>
        Name of Health Facility:{' '}
        <span className="border-b border-gray-500 inline-block w-64">&nbsp;</span>
      </div>
      <div>
        Name of Barangay:{' '}
        <span className="border-b border-gray-500 inline-block w-64">&nbsp;</span>
      </div>
    </div>
    <div className="space-y-1">
      <div>
        Name of Municipality/City:{' '}
        <span className="border-b border-gray-500 inline-block w-48">&nbsp;</span>
      </div>
      <div>
        Name of Province:{' '}
        <span className="border-b border-gray-500 inline-block w-52">&nbsp;</span>
      </div>
      <div>
        Projected Population of the Year:{' '}
        <span className="border-b border-gray-500 inline-block w-32">&nbsp;</span>
      </div>
      <div className="text-gray-500 text-xs italic">
        For submission to the next administrative level
      </div>
    </div>
  </div>
);

// ─── SECTION A: Child Care — Immunization ─────────────────────────────────────

// A.1 rows: left col (items 1–9) paired with right col (items 10–17)
const a1Left = [
  '1. Children protected at birth (CPAB)',
  '2. BCG (within 24 hours)',
  '3. BCG (more than 24 hours to 11 months and 29 days)',
  '4. Hep B antigen within 24 hrs after birth',
  '5. Hep B antigen more than 24 hrs up to 14 days',
  '6. DPT-HiB-HepB 1',
  '7. DPT-HiB-HepB 2',
  '8. DPT-HiB-HepB 3',
  '9. OPV 1',
];
const a1Right = [
  '10. OPV 2',
  '11. OPV 3',
  '12. IPV 1',
  '13. IPV 2',
  '14. PCV 1',
  '15. PCV 2',
  '16. PCV 3',
  '17. MMR 1',
  '', // empty to pad
];

// A.3 rows: previous year immunization
const a3Left = [
  '1. DPT-HiB-HepB 1',
  '2. DPT-HiB-HepB 2',
  '3. DPT-HiB-HepB 3',
  '4. OPV 1',
  '5. OPV 2',
  '6. OPV 3',
  '7. IPV 1',
  '8. IPV 2',
];
const a3Right = [
  '9. PCV 1',
  '10. PCV 2',
  '11. PCV 3',
  '12. MMR 1',
  '13. MMR 2',
  '14. FIC',
  '15. CIC',
  '',
];

// A.4 School and Community-Based Immunization
const a4Left = [
  '1. Grade 1 learners given Td',
  '2. Grade 1 learners given MR',
  '3. Grade 7 learners given Td',
  '4. Grade 7 learners given MR',
];
const a4Right = [
  '5. HPV 1 (SBI)',
  '6. HPV 1 (CBI)',
  '7. HPV 2 (CBI)',
  '',
];

const SectionA = () => {
  const maxA1 = Math.max(a1Left.length, a1Right.length);
  const maxA3 = Math.max(a3Left.length, a3Right.length);
  const maxA4 = Math.max(a4Left.length, a4Right.length);

  return (
    <div className="mb-6">
      <table className="w-full border-collapse text-xs">
        <tbody>
          <SectionHeader colSpan={10}>SECTION A. CHILD CARE AND SERVICES</SectionHeader>

          {/* ── A.1 ── */}
          <SubSectionHeader colSpan={10}>
            A.1. Immunization Services (0–11 months old current year)
          </SubSectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">Indicators</Th>
            <SexHeaders />
            <Th>Remarks</Th>
            <Th className="text-left w-5/12">Indicators</Th>
            <SexHeaders />
            <Th>Remarks</Th>
          </tr>
          {Array.from({ length: maxA1 }).map((_, i) => {
            const l = a1Left[i] ?? '';
            const r = a1Right[i] ?? '';
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

          {/* ── A.3 ── */}
          <SubSectionHeader colSpan={10}>
            A.3. Immunization Services (0–11 months of previous year)
          </SubSectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">Indicators</Th>
            <SexHeaders />
            <Th>Remarks</Th>
            <Th className="text-left w-5/12">Indicators</Th>
            <SexHeaders />
            <Th>Remarks</Th>
          </tr>
          {Array.from({ length: maxA3 }).map((_, i) => {
            const l = a3Left[i] ?? '';
            const r = a3Right[i] ?? '';
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

          {/* ── A.4 ── */}
          <SubSectionHeader colSpan={10}>
            A.4. School and Community-Based Immunization
          </SubSectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">Indicators</Th>
            <SexHeaders />
            <Th>Remarks</Th>
            <Th className="text-left w-5/12">Indicators</Th>
            <SexHeaders />
            <Th>Remarks</Th>
          </tr>
          {Array.from({ length: maxA4 }).map((_, i) => {
            const l = a4Left[i] ?? '';
            const r = a4Right[i] ?? '';
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
        </tbody>
      </table>
    </div>
  );
};

// ─── SECTION B: Non-Communicable Diseases ─────────────────────────────────────
const SectionB = () => (
  <div className="mb-6">
    <table className="w-full border-collapse text-xs">
      <tbody>
        <SectionHeader colSpan={8}>SECTION B. NON-COMMUNICABLE DISEASES</SectionHeader>

        {/* ── B1 ── */}
        <SubSectionHeader colSpan={8}>B1. Lifestyle Related</SubSectionHeader>
        <tr className="bg-gray-100">
          <Th className="text-left" colSpan={4}>Indicators</Th>
          <Th>Male</Th>
          <Th>Female</Th>
          <Th>Total</Th>
          <Th>Remarks</Th>
        </tr>
        <tr>
          <Td className="pl-4" colSpan={4}>
            1. Adults 20–59 years old who were risk assessed using the PhilPEN protocol
          </Td>
          <SexInputs />
          <InputCell />
        </tr>
        <tr>
          <Td className="pl-4" colSpan={4}>
            2. Senior Citizens 60 years old and above who were risk assessed using the PhilPEN protocol
          </Td>
          <SexInputs />
          <InputCell />
        </tr>

        {/* ── B2 & E3 side-by-side ── */}
        <tr className="bg-blue-100">
          <td colSpan={4} className="border border-gray-400 px-2 py-1 text-xs font-bold text-blue-900">
            B2. Cardiovascular Disease Prevention and Control
          </td>
          <td colSpan={4} className="border border-gray-400 px-2 py-1 text-xs font-bold text-blue-900">
            E3. Diabetes Mellitus Prevention and Control
          </td>
        </tr>

        {/* Column headers for B2 / E3 */}
        <tr className="bg-gray-100">
          <Th className="text-left w-5/12" colSpan={2}>Indicators</Th>
          <Th>Male</Th>
          <Th>Female</Th>
          <Th className="text-left w-5/12" colSpan={2}>Indicators</Th>
          <Th>Male</Th>
          <Th>Female</Th>
        </tr>

        {/* Cumulative rows */}
        <tr className="bg-yellow-50">
          <Td className="pl-2 text-xs italic" colSpan={2}>
            The total number of identified adult (20–59 years old) hypertensives
            (Sum of January to Previous Month)
          </Td>
          <InputCell />
          <td className="border border-gray-400" />
          <Td className="pl-2 text-xs italic" colSpan={2}>
            The total number of identified adult (20–59 years old) with Type II Diabetes
            (Sum of January to Previous Month)
          </Td>
          <InputCell />
          <td className="border border-gray-400" />
        </tr>
        <tr className="bg-yellow-50">
          <Td className="pl-2 text-xs italic" colSpan={2}>
            The total number of identified adult (20–59 years old) hypertensives in the current month
          </Td>
          <InputCell />
          <td className="border border-gray-400" />
          <Td className="pl-2 text-xs italic" colSpan={2}>
            The total number of identified adult (20–59 years old) with Type II Diabetes in the current month
          </Td>
          <InputCell />
          <td className="border border-gray-400" />
        </tr>

        {/* Row 1: Adults 20-59 identified */}
        <tr>
          <Td className="pl-4" colSpan={2}>
            1. Adults 20–59 years old who were identified as hypertensive using the PhilPEN protocol
          </Td>
          <InputCell />
          <InputCell />
          <Td className="pl-4" colSpan={2}>
            1. Adults 20–59 years old who were identified with Type II Diabetes using the PhilPEN protocol
          </Td>
          <InputCell />
          <InputCell />
        </tr>

        {/* Row 2: Provided antihypertensive */}
        <tr>
          <Td className="pl-4" colSpan={2}>
            2. Hypertensives 20–59 years old provided with antihypertensive medications
          </Td>
          <InputCell />
          <InputCell />
          <Td className="pl-4" colSpan={2}>
            2. Type II Diabetics 20–59 years old provided with antidiabetic medications
          </Td>
          <InputCell />
          <InputCell />
        </tr>
        <tr>
          <Td className="pl-8" colSpan={2}>2a. Provided by facility (100%)</Td>
          <InputCell />
          <td className="border border-gray-400" />
          <Td className="pl-8" colSpan={2}>2a. Provided by facility (100%)</Td>
          <InputCell />
          <td className="border border-gray-400" />
        </tr>
        <tr>
          <Td className="pl-8" colSpan={2}>2b. Out of pocket</Td>
          <InputCell />
          <td className="border border-gray-400" />
          <Td className="pl-8" colSpan={2}>2b. Out of pocket</Td>
          <InputCell />
          <td className="border border-gray-400" />
        </tr>
        <tr>
          <Td className="pl-8" colSpan={2}>2c. Both</Td>
          <InputCell />
          <td className="border border-gray-400" />
          <Td className="pl-8" colSpan={2}>2c. Both</Td>
          <InputCell />
          <td className="border border-gray-400" />
        </tr>

        {/* SC cumulative rows */}
        <tr className="bg-yellow-50">
          <Td className="pl-2 text-xs italic" colSpan={2}>
            The total number of identified SC (60 years old and above) hypertensives
            (Sum of January to Previous Month)
          </Td>
          <InputCell />
          <td className="border border-gray-400" />
          <Td className="pl-2 text-xs italic" colSpan={2}>
            The total number of identified SCs (60 years old and above) with Type II Diabetes
            (Sum of January to Previous Month)
          </Td>
          <InputCell />
          <td className="border border-gray-400" />
        </tr>
        <tr className="bg-yellow-50">
          <Td className="pl-2 text-xs italic" colSpan={2}>
            The total number of identified SC (60 years old and above) hypertensives in the current month
          </Td>
          <InputCell />
          <td className="border border-gray-400" />
          <Td className="pl-2 text-xs italic" colSpan={2}>
            The total number of identified SCs (60 years old and above) with Type II Diabetes in the current month
          </Td>
          <InputCell />
          <td className="border border-gray-400" />
        </tr>

        {/* Row 3: SC hypertensive */}
        <tr>
          <Td className="pl-4" colSpan={2}>
            3. Senior Citizens 60 years old and above who were identified as hypertensive using the PhilPEN protocol
          </Td>
          <InputCell />
          <InputCell />
          <Td className="pl-4" colSpan={2}>
            3. Senior Citizens 60 years old and above who were identified with Type II Diabetes using the PhilPEN protocol
          </Td>
          <InputCell />
          <InputCell />
        </tr>

        {/* Row 4: SC antihypertensive */}
        <tr>
          <Td className="pl-4" colSpan={2}>
            4. Hypertensives 60 years old and above provided with antihypertensive medications
          </Td>
          <InputCell />
          <InputCell />
          <Td className="pl-4" colSpan={2}>
            4. Type II Diabetics 60 years old and above provided with antidiabetic medications
          </Td>
          <InputCell />
          <InputCell />
        </tr>
        <tr>
          <Td className="pl-8" colSpan={2}>4a. Provided by facility (100%)</Td>
          <InputCell />
          <td className="border border-gray-400" />
          <Td className="pl-8" colSpan={2}>4a. Provided by facility (100%)</Td>
          <InputCell />
          <td className="border border-gray-400" />
        </tr>
        <tr>
          <Td className="pl-8" colSpan={2}>4b. Out of pocket</Td>
          <InputCell />
          <td className="border border-gray-400" />
          <Td className="pl-8" colSpan={2}>4b. Out of pocket</Td>
          <InputCell />
          <td className="border border-gray-400" />
        </tr>
        <tr>
          <Td className="pl-8" colSpan={2}>4c. Both</Td>
          <InputCell />
          <td className="border border-gray-400" />
          <Td className="pl-8" colSpan={2}>4c. Both</Td>
          <InputCell />
          <td className="border border-gray-400" />
        </tr>
      </tbody>
    </table>
  </div>
);

// ─── SECTION C: Vital Statistics ─────────────────────────────────────────────
const SectionC = () => (
  <div className="mb-6">
    <table className="w-full border-collapse text-xs">
      <tbody>
        <SectionHeader colSpan={11}>SECTION C. VITAL STATISTICS</SectionHeader>

        {/* C1 Mortality / C2 Natality header row */}
        <tr className="bg-gray-100">
          <Th className="text-left w-5/12">C1. Mortality — Indicators</Th>
          <Th>&lt;10</Th>
          <Th>15-19</Th>
          <Th>20-49</Th>
          <Th>TOTAL</Th>
          <Th>Remarks</Th>
          <Th className="text-left w-5/12">C2. Natality — Indicators</Th>
          <Th>Male</Th>
          <Th>Female</Th>
          <Th>Total</Th>
          <Th>Remarks</Th>
        </tr>

        {/* Paired rows */}
        {([
          ['1. Maternal Mortality - Total', '1. Live births (Total)'],
          ['a. Direct', '2. Adolescent Birth'],
          ['a1. Resident', '2a. <10 years old'],
          ['a2. Non-Resident', '2b. 10-14 years old'],
          ['b. Indirect', '2c. 15-19 years old'],
          ['b1. Resident', '3. Repeat Adolescent Birth'],
          ['b2. Non-Resident', '3a. 10-14 years old'],
          ['', '3b. 15-19 years old'],
        ] as [string, string][]).map(([l, r], i) => (
          <tr key={i}>
            <Td className="pl-4 w-5/12">{l}</Td>
            {l ? (
              <>
                <InputCell />
                <InputCell />
                <InputCell />
                <InputCell />
                <InputCell />
              </>
            ) : (
              <td colSpan={5} className="border border-gray-400" />
            )}
            <Td className="pl-4 w-5/12">{r}</Td>
            {r ? (
              <>
                <InputCell />
                <InputCell />
                <InputCell />
                <InputCell />
              </>
            ) : (
              <td colSpan={4} className="border border-gray-400" />
            )}
          </tr>
        ))}

        {/* Infant Mortality — uses Sex columns */}
        <tr className="bg-gray-100">
          <Th className="text-left" colSpan={6}>
            2. Infant Mortality (Male | Female | Total)
          </Th>
          <Th colSpan={5}>&nbsp;</Th>
        </tr>
        <tr>
          <Td className="pl-4">2. Infant Mortality</Td>
          <InputCell />
          <InputCell />
          <InputCell />
          <InputCell />
          <td className="border border-gray-400" />
          <td colSpan={5} className="border border-gray-400" />
        </tr>
      </tbody>
    </table>
  </div>
);

// ─── MAIN COMPONENT ───────────────────────────────────────────────────────────
export default function M28PAA() {
  const [activeSection, setActiveSection] = useState<string>('all');

  const sections = [
    { id: 'all', label: 'All' },
    { id: 'a', label: 'A. Child Care' },
    { id: 'b', label: 'B. Non-Communicable Diseases' },
    { id: 'c', label: 'C. Vital Statistics' },
  ];

  const show = (id: string) => activeSection === 'all' || activeSection === id;

  return (
    <div className="bg-white shadow-sm rounded-lg border border-gray-200 p-4">
      {/* Header */}
      <div className="mb-4 flex flex-wrap justify-between items-center gap-2">
        <div>
          <h2 className="text-xl font-bold text-gray-800">M2: 8PAA</h2>
          <p className="text-xs text-gray-500">FHSIS Monthly Report Form</p>
        </div>
        <div className="flex gap-2">
          <button
            onClick={() => window.print()}
            className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition text-sm"
          >
            Export / Print
          </button>
        </div>
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
        {show('a') && <SectionA />}
        {show('b') && <SectionB />}
        {show('c') && <SectionC />}
      </div>
    </div>
  );
}
