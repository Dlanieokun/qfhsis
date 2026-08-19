import React, { useMemo, useState } from 'react';

// ─── Location Data Shapes ────────────────────────────────────────────────────
interface Region { regCode: string; regDesc: string; }
interface Province { provCode: string; provDesc: string; regCode: string; }
interface Municipality { citymunCode: string; citymunDesc: string; provCode: string; }
interface Barangay { brgyCode: string; brgyDesc: string; citymunCode: string; }

interface M28PAAProps {
  regions?: Region[];
  provinces?: Province[];
  municipalities?: Municipality[];
  barangays?: Barangay[];
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

const InputCell = ({
  className = '',
  value,
}: {
  className?: string;
  value?: number | string | null;
}) => (
  <td className={`border border-gray-400 px-1 py-0.5 ${className}`}>
    <input
      type="number"
      className="w-full text-center text-xs border-0 outline-none bg-transparent"
      defaultValue={value ?? ''}
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

// Sex columns helpers
type SexVals = { male?: number | string | null; female?: number | string | null; total?: number | string | null };

const SexHeaders = () => (
  <>
    <Th>Male</Th>
    <Th>Female</Th>
    <Th>Total</Th>
  </>
);
const SexInputs = ({ values }: { values?: SexVals }) => (
  <>
    <InputCell value={values?.male} />
    <InputCell value={values?.female} />
    <InputCell value={values?.total} />
  </>
);

// ─── Location & Period Filter Header ─────────────────────────────────────────
const months = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];

const FormHeader = ({
  regions,
  provinces,
  municipalities,
  barangays,
}: {
  regions: Region[];
  provinces: Province[];
  municipalities: Municipality[];
  barangays: Barangay[];
}) => {
  const [selectedMonth, setSelectedMonth] = useState('');
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear().toString());
  const [selectedRegion, setSelectedRegion] = useState('');
  const [selectedProvince, setSelectedProvince] = useState('');
  const [selectedMunicipality, setSelectedMunicipality] = useState('');
  const [selectedBarangay, setSelectedBarangay] = useState('');

  const filteredProvinces = useMemo(
    () => provinces.filter((p) => p.regCode === selectedRegion),
    [selectedRegion, provinces],
  );
  const filteredMunicipalities = useMemo(
    () => municipalities.filter((m) => m.provCode === selectedProvince),
    [selectedProvince, municipalities],
  );
  const filteredBarangays = useMemo(
    () => barangays.filter((b) => b.citymunCode === selectedMunicipality),
    [selectedMunicipality, barangays],
  );

  return (
    <div className="mb-4 grid grid-cols-1 gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm md:grid-cols-3">
      <div className="space-y-1">
        <div className="flex items-center gap-2">
          <label className="w-40 shrink-0">FHSIS REPORT for the Month:</label>
          <select
            className="w-full rounded border border-gray-300 px-2 py-1 text-sm outline-none focus:border-blue-500"
            value={selectedMonth}
            onChange={(e) => setSelectedMonth(e.target.value)}
          >
            <option value="">Select Month</option>
            {months.map((m) => (
              <option key={m} value={m}>{m}</option>
            ))}
          </select>
        </div>
        <div className="flex items-center gap-2">
          <label className="w-40 shrink-0">Year:</label>
          <input
            type="text"
            className="w-full rounded border border-gray-300 px-2 py-1 text-sm text-center outline-none focus:border-blue-500"
            value={selectedYear}
            onChange={(e) => setSelectedYear(e.target.value)}
            placeholder="YYYY"
          />
        </div>
      </div>

      <div className="space-y-1">
        <div className="flex items-center gap-2">
          <label className="w-32 shrink-0">Region:</label>
          <select
            className="w-full rounded border border-gray-300 px-2 py-1 text-sm outline-none focus:border-blue-500"
            value={selectedRegion}
            onChange={(e) => {
              setSelectedRegion(e.target.value);
              setSelectedProvince('');
              setSelectedMunicipality('');
              setSelectedBarangay('');
            }}
          >
            <option value="">Select Region</option>
            {regions.map((r) => (
              <option key={r.regCode} value={r.regCode}>{r.regDesc}</option>
            ))}
          </select>
        </div>
        <div className="flex items-center gap-2">
          <label className="w-32 shrink-0">Province:</label>
          <select
            className="w-full rounded border border-gray-300 px-2 py-1 text-sm outline-none focus:border-blue-500 disabled:bg-gray-100"
            value={selectedProvince}
            disabled={!selectedRegion}
            onChange={(e) => {
              setSelectedProvince(e.target.value);
              setSelectedMunicipality('');
              setSelectedBarangay('');
            }}
          >
            <option value="">Select Province</option>
            {filteredProvinces.map((p) => (
              <option key={p.provCode} value={p.provCode}>{p.provDesc}</option>
            ))}
          </select>
        </div>
      </div>

      <div className="space-y-1">
        <div className="flex items-center gap-2">
          <label className="w-32 shrink-0">Municipality:</label>
          <select
            className="w-full rounded border border-gray-300 px-2 py-1 text-sm outline-none focus:border-blue-500 disabled:bg-gray-100"
            value={selectedMunicipality}
            disabled={!selectedProvince}
            onChange={(e) => {
              setSelectedMunicipality(e.target.value);
              setSelectedBarangay('');
            }}
          >
            <option value="">Select Municipality</option>
            {filteredMunicipalities.map((m) => (
              <option key={m.citymunCode} value={m.citymunCode}>{m.citymunDesc}</option>
            ))}
          </select>
        </div>
        <div className="flex items-center gap-2">
          <label className="w-32 shrink-0">Barangay:</label>
          <select
            className="w-full rounded border border-gray-300 px-2 py-1 text-sm outline-none focus:border-blue-500 disabled:bg-gray-100"
            value={selectedBarangay}
            disabled={!selectedMunicipality}
            onChange={(e) => setSelectedBarangay(e.target.value)}
          >
            <option value="">Select Barangay</option>
            {filteredBarangays.map((b) => (
              <option key={b.brgyCode} value={b.brgyCode}>{b.brgyDesc}</option>
            ))}
          </select>
        </div>
      </div>
    </div>
  );
};

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
const a1LeftKeys = [
  'cpab', 'bcg24h', 'bcgLate', 'hepB24h', 'hepBLate', 'dpt1', 'dpt2', 'dpt3', 'opv1',
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
const a1RightKeys = [
  'opv2', 'opv3', 'ipv1', 'ipv2', 'pcv1', 'pcv2', 'pcv3', 'mmr1', '',
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
const a3LeftKeys = [
  'dpt1', 'dpt2', 'dpt3', 'opv1', 'opv2', 'opv3', 'ipv1', 'ipv2',
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
const a3RightKeys = [
  'pcv1', 'pcv2', 'pcv3', 'mmr1', 'mmr2', 'fic', 'cic', '',
];

// A.4 School and Community-Based Immunization
const a4Left = [
  '1. Grade 1 learners given Td',
  '2. Grade 1 learners given MR',
  '3. Grade 7 learners given Td',
  '4. Grade 7 learners given MR',
];
const a4LeftKeys = [
  'grade1Td', 'grade1Mr', 'grade7Td', 'grade7Mr',
];
const a4Right = [
  '5. HPV 1 (SBI)',
  '6. HPV 1 (CBI)',
  '7. HPV 2 (CBI)',
  '',
];
const a4RightKeys = [
  'hpv1Sbi', 'hpv1Cbi', 'hpv2Cbi', '',
];

const SectionA = ({ data }: { data?: any }) => {
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
            const lKey = a1LeftKeys[i] ?? '';
            const rKey = a1RightKeys[i] ?? '';
            const lVals = lKey ? data?.a1?.[lKey] : undefined;
            const rVals = rKey ? data?.a1?.[rKey] : undefined;
            return (
              <tr key={i}>
                <Td className="pl-4 w-5/12">{l}</Td>
                {l ? (
                  <><SexInputs values={lVals} /><InputCell /></>
                ) : (
                  <td colSpan={4} className="border border-gray-400" />
                )}
                <Td className="pl-4 w-5/12">{r}</Td>
                {r ? (
                  <><SexInputs values={rVals} /><InputCell /></>
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
            const lKey = a3LeftKeys[i] ?? '';
            const rKey = a3RightKeys[i] ?? '';
            const lVals = lKey ? data?.a3?.[lKey] : undefined;
            const rVals = rKey ? data?.a3?.[rKey] : undefined;
            return (
              <tr key={i}>
                <Td className="pl-4 w-5/12">{l}</Td>
                {l ? (
                  <><SexInputs values={lVals} /><InputCell /></>
                ) : (
                  <td colSpan={4} className="border border-gray-400" />
                )}
                <Td className="pl-4 w-5/12">{r}</Td>
                {r ? (
                  <><SexInputs values={rVals} /><InputCell /></>
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
            const lKey = a4LeftKeys[i] ?? '';
            const rKey = a4RightKeys[i] ?? '';
            const lVals = lKey ? data?.a4?.[lKey] : undefined;
            const rVals = rKey ? data?.a4?.[rKey] : undefined;
            return (
              <tr key={i}>
                <Td className="pl-4 w-5/12">{l}</Td>
                {l ? (
                  <><SexInputs values={lVals} /><InputCell /></>
                ) : (
                  <td colSpan={4} className="border border-gray-400" />
                )}
                <Td className="pl-4 w-5/12">{r}</Td>
                {r ? (
                  <><SexInputs values={rVals} /><InputCell /></>
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
const SectionB = ({ data }: { data?: any }) => (
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
          <SexInputs values={data?.lifestyle2059} />
          <InputCell />
        </tr>
        <tr>
          <Td className="pl-4" colSpan={4}>
            2. Senior Citizens 60 years old and above who were risk assessed using the PhilPEN protocol
          </Td>
          <SexInputs values={data?.lifestyle60plus} />
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
          <InputCell value={data?.cvd2059?.male} />
          <InputCell value={data?.cvd2059?.female} />
          <Td className="pl-4" colSpan={2}>
            1. Adults 20–59 years old who were identified with Type II Diabetes using the PhilPEN protocol
          </Td>
          <InputCell value={data?.dm2059?.male} />
          <InputCell value={data?.dm2059?.female} />
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
          <InputCell value={data?.cvd60plus?.male} />
          <InputCell value={data?.cvd60plus?.female} />
          <Td className="pl-4" colSpan={2}>
            3. Senior Citizens 60 years old and above who were identified with Type II Diabetes using the PhilPEN protocol
          </Td>
          <InputCell value={data?.dm60plus?.male} />
          <InputCell value={data?.dm60plus?.female} />
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
// Left (mortality) row keys -> maps to data.mortality[key], values pulled in
// the order ['10-14','15-19','20-49','total'] to line up with the 4 InputCells.
const c1LeftKeys = [
  'maternalTotal', 'direct', 'directResident', 'directNonResident',
  'indirect', 'indirectResident', 'indirectNonResident', '',
];
// Right (natality) row keys -> maps to data.natality[key] {male,female,total}
const c1RightKeys = [
  'liveBirths', 'adolescentTotal', 'adolescentUnder10', 'adolescent10to14',
  'adolescent15to19', 'repeatAdolescentTotal', 'repeat10to14', 'repeat15to19',
];

const ageVals = (obj: any): (number | string | undefined)[] =>
  obj ? [obj['10-14'], obj['15-19'], obj['20-49'], obj['total']] : [undefined, undefined, undefined, undefined];

const SectionC = ({ data }: { data?: any }) => (
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
        ] as [string, string][]).map(([l, r], i) => {
          const lKey = c1LeftKeys[i] ?? '';
          const rKey = c1RightKeys[i] ?? '';
          const [v0, v1, v2, v3] = ageVals(lKey ? data?.mortality?.[lKey] : undefined);
          const rVals = rKey ? data?.natality?.[rKey] : undefined;
          return (
            <tr key={i}>
              <Td className="pl-4 w-5/12">{l}</Td>
              {l ? (
                <>
                  <InputCell value={v0} />
                  <InputCell value={v1} />
                  <InputCell value={v2} />
                  <InputCell value={v3} />
                  <InputCell />
                </>
              ) : (
                <td colSpan={5} className="border border-gray-400" />
              )}
              <Td className="pl-4 w-5/12">{r}</Td>
              {r ? (
                <>
                  <InputCell value={rVals?.male} />
                  <InputCell value={rVals?.female} />
                  <InputCell value={rVals?.total} />
                  <InputCell />
                </>
              ) : (
                <td colSpan={4} className="border border-gray-400" />
              )}
            </tr>
          );
        })}

        {/* Infant Mortality — uses Sex columns */}
        <tr className="bg-gray-100">
          <Th className="text-left" colSpan={6}>
            2. Infant Mortality (Male | Female | Total)
          </Th>
          <Th colSpan={5}>&nbsp;</Th>
        </tr>
        <tr>
          <Td className="pl-4">2. Infant Mortality</Td>
          <InputCell value={data?.mortality?.infant?.male} />
          <InputCell value={data?.mortality?.infant?.female} />
          <InputCell value={data?.mortality?.infant?.total} />
          <InputCell />
          <td className="border border-gray-400" />
          <td colSpan={5} className="border border-gray-400" />
        </tr>
      </tbody>
    </table>
  </div>
);

// ─── Filter Controller Component ──────────────────────────────────────────────
interface FilterState {
  month: string;
  year: string;
  region: string;
  province: string;
  municipality: string;
  barangay: string;
}

const FilterControls = ({
  filterState,
  setFilterState,
  isFilterOpen,
  setIsFilterOpen,
  onClearFilters,
  onApplyFilters,
  isLoading = false,
  error = null,
}: {
  filterState: FilterState;
  setFilterState: (state: FilterState) => void;
  isFilterOpen: boolean;
  setIsFilterOpen: (open: boolean) => void;
  onClearFilters: () => void;
  onApplyFilters: () => void;
  isLoading?: boolean;
  error?: string | null;
}) => {
  return (
    <div className="mb-4 space-y-2">
      {/* Filter Button Row */}
      <div className="flex gap-2 flex-wrap">
        <button
          onClick={() => setIsFilterOpen(!isFilterOpen)}
          className={`px-3 py-2 rounded text-sm font-medium border transition flex items-center gap-2 ${
            isFilterOpen
              ? 'bg-blue-100 text-blue-700 border-blue-400'
              : 'bg-gray-100 text-gray-700 border-gray-300 hover:bg-gray-200'
          }`}
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
          </svg>
          {isFilterOpen ? 'Hide Filters' : 'Show Filters'}
        </button>

        {/* Clear Filters Button */}
        <button
          onClick={onClearFilters}
          disabled={isLoading}
          className="px-3 py-2 rounded text-sm font-medium bg-red-50 text-red-700 border border-red-300 hover:bg-red-100 transition disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Clear Filters
        </button>

        {/* Apply Filters Button */}
        <button
          onClick={onApplyFilters}
          disabled={isLoading}
          className="px-3 py-2 rounded text-sm font-medium bg-green-600 text-white hover:bg-green-700 transition disabled:opacity-60 disabled:cursor-not-allowed flex items-center gap-2"
        >
          {isLoading && (
            <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
            </svg>
          )}
          {isLoading ? 'Applying...' : 'Apply Filters'}
        </button>
      </div>

      {/* Fetch Error */}
      {error && (
        <div className="bg-red-50 border border-red-200 rounded px-3 py-2 text-xs text-red-700">
          {error}
        </div>
      )}

      {/* Active Filters Display */}
      {(filterState.month || filterState.year || filterState.region || 
        filterState.province || filterState.municipality || filterState.barangay) && (
        <div className="bg-blue-50 border border-blue-200 rounded px-3 py-2 text-xs">
          <p className="font-semibold text-blue-900 mb-1">Active Filters:</p>
          <div className="flex flex-wrap gap-2">
            {filterState.month && (
              <span className="inline-flex items-center gap-1 bg-blue-200 text-blue-800 px-2 py-0.5 rounded">
                Month: {filterState.month}
              </span>
            )}
            {filterState.year && (
              <span className="inline-flex items-center gap-1 bg-blue-200 text-blue-800 px-2 py-0.5 rounded">
                Year: {filterState.year}
              </span>
            )}
            {filterState.region && (
              <span className="inline-flex items-center gap-1 bg-blue-200 text-blue-800 px-2 py-0.5 rounded">
                Region: {filterState.region}
              </span>
            )}
            {filterState.province && (
              <span className="inline-flex items-center gap-1 bg-blue-200 text-blue-800 px-2 py-0.5 rounded">
                Province: {filterState.province}
              </span>
            )}
            {filterState.municipality && (
              <span className="inline-flex items-center gap-1 bg-blue-200 text-blue-800 px-2 py-0.5 rounded">
                Municipality: {filterState.municipality}
              </span>
            )}
            {filterState.barangay && (
              <span className="inline-flex items-center gap-1 bg-blue-200 text-blue-800 px-2 py-0.5 rounded">
                Barangay: {filterState.barangay}
              </span>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

// ─── MAIN COMPONENT ───────────────────────────────────────────────────────────
export default function M28PAA({
  regions = [],
  provinces = [],
  municipalities = [],
  barangays = [],
}: M28PAAProps) {
  const [activeSection, setActiveSection] = useState<string>('all');
  const [isFilterOpen, setIsFilterOpen] = useState<boolean>(true);
  const [filterState, setFilterState] = useState<FilterState>({
    month: '',
    year: new Date().getFullYear().toString(),
    region: '',
    province: '',
    municipality: '',
    barangay: '',
  });
  const [appliedFilters, setAppliedFilters] = useState<FilterState>(filterState);
  const [reportData, setReportData] = useState<any>(null);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [fetchError, setFetchError] = useState<string | null>(null);
  // Bumped on every successful fetch / clear so the (uncontrolled) InputCell
  // grids remount and pick up fresh defaultValue props from reportData.
  const [fetchNonce, setFetchNonce] = useState<number>(0);

  const sections = [
    { id: 'all', label: 'All' },
    { id: 'a', label: 'A. Child Care' },
    { id: 'b', label: 'B. Non-Communicable Diseases' },
    { id: 'c', label: 'C. Vital Statistics' },
  ];

  const show = (id: string) => activeSection === 'all' || activeSection === id;

  const handleClearFilters = () => {
    const cleared: FilterState = {
      month: '',
      year: new Date().getFullYear().toString(),
      region: '',
      province: '',
      municipality: '',
      barangay: '',
    };
    setFilterState(cleared);
    setAppliedFilters(cleared);
    setReportData(null);
    setFetchError(null);
    setFetchNonce((n) => n + 1);
  };

  // Calls GET /api/reports/m28paa (see routes/api.php + M28PAAController)
  // with the current filter selections as query params.
  const fetchReportData = async (filters: FilterState) => {
    if (!filters.year) {
      setFetchError('Please select a year before applying filters.');
      return;
    }

    setIsLoading(true);
    setFetchError(null);

    try {
      const params = new URLSearchParams();
      params.set('year', filters.year);

      // Backend expects month as 1-12; frontend stores the month name.
      const monthIndex = months.indexOf(filters.month);
      if (monthIndex !== -1) {
        params.set('month', String(monthIndex + 1));
      }
      if (filters.region) params.set('region', filters.region);
      if (filters.province) params.set('province', filters.province);
      if (filters.municipality) params.set('municipality', filters.municipality);
      if (filters.barangay) params.set('barangay', filters.barangay);

      const response = await fetch(`/qfhsis/public/api/reports/m28paa?${params.toString()}`, {
        method: 'GET',
        headers: { Accept: 'application/json' },
      });

      if (!response.ok) {
        const body = await response.json().catch(() => null);
        const message = body?.message || `Request failed with status ${response.status}`;
        throw new Error(message);
      }

      const json = await response.json();
      setReportData(json.data ?? null);
      setFetchNonce((n) => n + 1);
    } catch (err) {
      setFetchError(err instanceof Error ? err.message : 'Failed to fetch report data.');
      setReportData(null);
    } finally {
      setIsLoading(false);
    }
  };

  const handleApplyFilters = () => {
    setAppliedFilters(filterState);
    fetchReportData(filterState);
  };

  const handleFilterChange = (key: keyof FilterState, value: string) => {
    setFilterState((prev) => ({
      ...prev,
      [key]: value,
    }));
  };

  const handleRegionChange = (value: string) => {
    handleFilterChange('region', value);
    handleFilterChange('province', '');
    handleFilterChange('municipality', '');
    handleFilterChange('barangay', '');
  };

  const handleProvinceChange = (value: string) => {
    handleFilterChange('province', value);
    handleFilterChange('municipality', '');
    handleFilterChange('barangay', '');
  };

  const handleMunicipalityChange = (value: string) => {
    handleFilterChange('municipality', value);
    handleFilterChange('barangay', '');
  };

  const filteredProvinces = useMemo(
    () => provinces.filter((p) => p.regCode === filterState.region),
    [filterState.region, provinces],
  );
  const filteredMunicipalities = useMemo(
    () => municipalities.filter((m) => m.provCode === filterState.province),
    [filterState.province, municipalities],
  );
  const filteredBarangays = useMemo(
    () => barangays.filter((b) => b.citymunCode === filterState.municipality),
    [filterState.municipality, barangays],
  );

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

      {/* Filter Controls */}
      <FilterControls
        filterState={filterState}
        setFilterState={setFilterState}
        isFilterOpen={isFilterOpen}
        setIsFilterOpen={setIsFilterOpen}
        onClearFilters={handleClearFilters}
        onApplyFilters={handleApplyFilters}
        isLoading={isLoading}
        error={fetchError}
      />

      {/* Collapsible Filter Panel */}
      {isFilterOpen && (
        <div className="mb-4 grid grid-cols-1 gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm md:grid-cols-3">
          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <label className="w-40 shrink-0 font-medium text-gray-700">FHSIS REPORT for the Month:</label>
              <select
                className="w-full rounded border border-gray-300 px-2 py-1 text-sm outline-none focus:border-blue-500"
                value={filterState.month}
                onChange={(e) => handleFilterChange('month', e.target.value)}
              >
                <option value="">Select Month</option>
                {months.map((m) => (
                  <option key={m} value={m}>{m}</option>
                ))}
              </select>
            </div>
            <div className="flex items-center gap-2">
              <label className="w-40 shrink-0 font-medium text-gray-700">Year:</label>
              <input
                type="text"
                className="w-full rounded border border-gray-300 px-2 py-1 text-sm text-center outline-none focus:border-blue-500"
                value={filterState.year}
                onChange={(e) => handleFilterChange('year', e.target.value)}
                placeholder="YYYY"
              />
            </div>
          </div>

          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <label className="w-32 shrink-0 font-medium text-gray-700">Region:</label>
              <select
                className="w-full rounded border border-gray-300 px-2 py-1 text-sm outline-none focus:border-blue-500"
                value={filterState.region}
                onChange={(e) => handleRegionChange(e.target.value)}
              >
                <option value="">Select Region</option>
                {regions.map((r) => (
                  <option key={r.regCode} value={r.regCode}>{r.regDesc}</option>
                ))}
              </select>
            </div>
            <div className="flex items-center gap-2">
              <label className="w-32 shrink-0 font-medium text-gray-700">Province:</label>
              <select
                className="w-full rounded border border-gray-300 px-2 py-1 text-sm outline-none focus:border-blue-500 disabled:bg-gray-100"
                value={filterState.province}
                disabled={!filterState.region}
                onChange={(e) => handleProvinceChange(e.target.value)}
              >
                <option value="">Select Province</option>
                {filteredProvinces.map((p) => (
                  <option key={p.provCode} value={p.provCode}>{p.provDesc}</option>
                ))}
              </select>
            </div>
          </div>

          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <label className="w-32 shrink-0 font-medium text-gray-700">Municipality:</label>
              <select
                className="w-full rounded border border-gray-300 px-2 py-1 text-sm outline-none focus:border-blue-500 disabled:bg-gray-100"
                value={filterState.municipality}
                disabled={!filterState.province}
                onChange={(e) => handleMunicipalityChange(e.target.value)}
              >
                <option value="">Select Municipality</option>
                {filteredMunicipalities.map((m) => (
                  <option key={m.citymunCode} value={m.citymunCode}>{m.citymunDesc}</option>
                ))}
              </select>
            </div>
            <div className="flex items-center gap-2">
              <label className="w-32 shrink-0 font-medium text-gray-700">Barangay:</label>
              <select
                className="w-full rounded border border-gray-300 px-2 py-1 text-sm outline-none focus:border-blue-500 disabled:bg-gray-100"
                value={filterState.barangay}
                disabled={!filterState.municipality}
                onChange={(e) => handleFilterChange('barangay', e.target.value)}
              >
                <option value="">Select Barangay</option>
                {filteredBarangays.map((b) => (
                  <option key={b.brgyCode} value={b.brgyCode}>{b.brgyDesc}</option>
                ))}
              </select>
            </div>
          </div>
        </div>
      )}

      {/* Report fetch status */}
      {reportData && !isLoading && (
        <div className="mb-4 bg-green-50 border border-green-200 rounded px-3 py-2 text-xs text-green-800">
          Report data loaded for {appliedFilters.month || 'Year-to-date'} {appliedFilters.year} — values populated into the grid below.
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



      {/* Sections */}
      <div className="overflow-x-auto space-y-2" key={fetchNonce}>
        {show('a') && <SectionA data={reportData?.sectionA} />}
        {show('b') && <SectionB data={reportData?.sectionB} />}
        {show('c') && <SectionC data={reportData?.sectionC} />}
      </div>
    </div>
  );
}