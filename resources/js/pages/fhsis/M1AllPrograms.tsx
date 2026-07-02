import React, { useState } from 'react';

// ─── Data shape coming from PhoController@pho via Inertia props ─────────────
interface FamilyPlanningBrackets {
  '10-14': number;
  '15-19': number;
  '20-49': number;
  total: number;
}

interface FamilyPlanningData {
  demandSatisfied: FamilyPlanningBrackets;
  currentUsersByMethod: Record<string, FamilyPlanningBrackets>;
}

// Section B (Maternal Care) data shape from PhoController@getMaternalCareData
interface AgeBrackets {
  '10-14': number;
  '15-19': number;
  '20-49': number;
  total: number;
}

interface SexBrackets {
  male: number;
  female: number;
  total: number;
}

interface MaternalCareData {
  prenatal: Record<string, AgeBrackets>;
  intrapartum: Record<string, SexBrackets>;
  postpartum: Record<string, AgeBrackets>;
}

// Section C (Child Care) data shape from PhoController@getChildCareData
interface ChildCareData {
  imm0_11: Record<string, SexBrackets>;
  immPrev: Record<string, SexBrackets>;
  schoolImm: Record<string, SexBrackets>;
  nutrition: Record<string, SexBrackets>;
  nutrition2: Record<string, SexBrackets>;
  mgmtSick: Record<string, SexBrackets>;
}

interface M1AllProgramsProps {
  familyPlanning?: FamilyPlanningData;
  maternalCare?: MaternalCareData;
  childCare?: ChildCareData;
}

// ─── Types ───────────────────────────────────────────────────────────────────
interface RowDef {
  label: string;
  indent?: number;    
  type?: 'section' | 'subSection' | 'header' | 'row';
  colSpan?: { ageGroup?: boolean; sex?: boolean; wra?: boolean; custom?: string[] };
}

// ─── Reusable cell helpers ────────────────────────────────────────────────────
const Th = ({ children, className = '', rowSpan, colSpan }: { children?: React.ReactNode; className?: string; rowSpan?: number; colSpan?: number }) => (
  <th rowSpan={rowSpan} colSpan={colSpan} className={`border border-gray-400 px-2 py-1 text-center text-xs font-semibold bg-gray-200 ${className}`}>
    {children}
  </th>
);

const Td = ({ children, className = '', colSpan, rowSpan }: { children?: React.ReactNode; className?: string; colSpan?: number; rowSpan?: number }) => (
  <td colSpan={colSpan} rowSpan={rowSpan} className={`border border-gray-400 px-2 py-1 text-xs ${className}`}>
    {children}
  </td>
);

// InputCell is now pre-filled from `value` (real DB-backed data) when provided,
// while remaining a normal editable input (defaultValue = uncontrolled).
const InputCell = ({ className = '', value }: { className?: string; value?: number | string }) => (
  <td className={`border border-gray-400 px-1 py-0.5 ${className}`}>
    <input
      type="number"
      className="w-full text-center text-xs border-0 outline-none bg-transparent"
      defaultValue={value ?? ''}
    />
  </td>
);

const SectionHeader = ({ children, colSpan }: { children: React.ReactNode; colSpan: number }) => (
  <tr className="bg-blue-700">
    <td colSpan={colSpan} className="border border-gray-400 px-2 py-1 text-sm font-bold text-white text-center">
      {children}
    </td>
  </tr>
);

const SubSectionHeader = ({ children, colSpan }: { children: React.ReactNode; colSpan: number }) => (
  <tr className="bg-blue-100">
    <td colSpan={colSpan} className="border border-gray-400 px-2 py-1 text-xs font-bold text-blue-900">
      {children}
    </td>
  </tr>
);

// ─── Shared age-group columns: 10-14 | 15-19 | 20-49 | TOTAL ────────────────
const AgeGroupHeaders = () => (
  <>
    <Th>10-14</Th><Th>15-19</Th><Th>20-49</Th><Th>TOTAL</Th>
  </>
);
// Sex columns: Male | Female | Total
const SexHeaders = () => (
  <>
    <Th>Male</Th><Th>Female</Th><Th>Total</Th>
  </>
);
const AgeInputs = () => (
  <>
    <InputCell /><InputCell /><InputCell /><InputCell />
  </>
);
const SexInputs = () => (
  <>
    <InputCell /><InputCell /><InputCell />
  </>
);

// ─── HEADER INFO ─────────────────────────────────────────────────────────────
const FormHeader = () => (
  <div className="mb-4 grid grid-cols-2 gap-4 text-sm">
    <div className="space-y-1">
      <div>FHSIS REPORT for the Month: <span className="border-b border-gray-500 inline-block w-32">&nbsp;</span> Year: <span className="border-b border-gray-500 inline-block w-20">&nbsp;</span></div>
      <div>Name of Barangay: <span className="border-b border-gray-500 inline-block w-64">&nbsp;</span></div>
      <div>Name of BHS: <span className="border-b border-gray-500 inline-block w-64">&nbsp;</span></div>
    </div>
    <div className="space-y-1">
      <div>Name of Municipality/City: <span className="border-b border-gray-500 inline-block w-48">&nbsp;</span></div>
      <div>Name of Province: <span className="border-b border-gray-500 inline-block w-52">&nbsp;</span></div>
      <div>Projected Population of the Year: <span className="border-b border-gray-500 inline-block w-32">&nbsp;</span></div>
    </div>
  </div>
);

// ─── SECTION A: Family Planning ───────────────────────────────────────────────
// Maps the display label used in the fpMethods list to the backend key
// returned in familyPlanning.currentUsersByMethod (see PhoController@getFamilyPlanningData).
const methodKeyMap: Record<string, string> = {
  '1. BTL': 'btl',
  '2. NSV': 'nsv',
  '3. Condom': 'condom',
  'a. Pills-POP': 'pills-pop',
  'b. Pills-COC': 'pills-coc',
  '5. Injectables (DMPA)': 'injectable',
  'a. Implants-Interval': 'implant-interval',
  'b. Implants-PP': 'implant-pp',
  'a. IUD-Interval': 'iud-interval',
  'b. IUD-PP': 'iud-pp',
  '8. NFP-LAM': 'lam',
  '9. NFP-BBT': 'bbt',
  '10. NFP-CMM': 'cmm',
  '11. NFP-STM': 'stm',
  '12. NFP-SDM': 'sdm',
};

const SectionA = ({ familyPlanning }: { familyPlanning?: FamilyPlanningData }) => {
  const fpMethods = [
    '1. BTL', '2. NSV', '3. Condom', '4. Pills', 'a. Pills-POP', 'b. Pills-COC',
    '5. Injectables (DMPA)', '6. Implant', 'a. Implants-Interval', 'b. Implants-PP',
    '7. IUD', 'a. IUD-Interval', 'b. IUD-PP',
    '8. NFP-LAM', '9. NFP-BBT', '10. NFP-CMM', '11. NFP-STM', '12. NFP-SDM',
    'Total Current Users',
  ];

  // "Total Current Users" row = sum of every method's counts, per bracket.
  const totalsByBracket: FamilyPlanningBrackets = { '10-14': 0, '15-19': 0, '20-49': 0, total: 0 };
  if (familyPlanning) {
    Object.values(familyPlanning.currentUsersByMethod).forEach((counts) => {
      totalsByBracket['10-14'] += counts['10-14'] ?? 0;
      totalsByBracket['15-19'] += counts['15-19'] ?? 0;
      totalsByBracket['20-49'] += counts['20-49'] ?? 0;
      totalsByBracket.total += counts.total ?? 0;
    });
  }

  return (
    <div className="mb-6 space-y-4">
      {/* Table A1: 6 Columns */}
      <table className="w-full border-collapse text-xs">
        <tbody>
          <SectionHeader colSpan={6}>SECTION A. FAMILY PLANNING SERVICES FOR WOMEN OF REPRODUCTIVE AGE</SectionHeader>

          {/* A1 */}
          <tr className="bg-gray-100">
            <td colSpan={6} className="border border-gray-400 px-2 py-1 font-semibold text-xs">
              A1. Demand Satisfied
            </td>
          </tr>
          <tr className="bg-gray-50">
            <Th className="text-left w-1/2">Indicators</Th>
            <Th>Age Group</Th>
            <Th colSpan={3}>Total WRA 15-49 yrs old</Th>
            <Th>Remarks</Th>
          </tr>
          <tr className="bg-gray-50">
            <Th className="text-left"></Th>
            <Th>10-14 yrs old</Th>
            <Th>15-19 yrs old</Th>
            <Th colSpan={2}>20-49 yrs old</Th>
            <Th></Th>
          </tr>
          <tr>
            <Td className="w-1/2 pl-4">
              1. No. of women of reproductive age (WRA) 15-49 years old who have demand for Family Planning (FP) and currently using, or whose partner is currently using, any modern FP methods
            </Td>
            <InputCell value={familyPlanning?.demandSatisfied['10-14']} />
            <InputCell value={familyPlanning?.demandSatisfied['15-19']} />
            <InputCell value={familyPlanning?.demandSatisfied['20-49']} />
            <InputCell value={familyPlanning?.demandSatisfied.total} />
            <InputCell />
          </tr>
        </tbody>
      </table>

      {/* Table A2: 21 Columns */}
      <table className="w-full border-collapse text-xs">
        <tbody>
          <tr className="bg-gray-100">
            <Th className="text-left" rowSpan={3}>A2. Modern FP Methods</Th>
            <Th colSpan={4}>Current Users (Beginning of the Month)</Th>
            <Th colSpan={4}>Acceptors</Th>
            <Th colSpan={4}>Drop-outs (Present Month)</Th>
            <Th colSpan={4}>Current User (End of the Month)</Th>
            <Th colSpan={4}>New Acceptors (Present Month)</Th>
          </tr>
          <tr className="bg-gray-50">
            <Th colSpan={4}>Age Group / TOTAL</Th>
            <Th colSpan={4}>New Acceptors (Previous Month) / Other Acceptors (Present Month) / TOTAL</Th>
            <Th colSpan={4}>Age Group / TOTAL</Th>
            <Th colSpan={4}>Age Group / TOTAL</Th>
            <Th colSpan={4}>Age Group / TOTAL</Th>
          </tr>
          <tr className="bg-gray-50">
            {[0, 1, 2, 3, 4].map(i => (
              <React.Fragment key={i}>
                <Th>10-14</Th><Th>15-19</Th><Th>20-49</Th><Th>TOTAL</Th>
              </React.Fragment>
            ))}
          </tr>
          {fpMethods.map((m, i) => {
            const isTotal = m.startsWith('Total');
            const key = methodKeyMap[m];
            // Only the "Current User (End of the Month)" group (index 3) is
            // wired to real data right now — see PhoController@getFamilyPlanningData.
            const counts: FamilyPlanningBrackets | undefined = isTotal
              ? totalsByBracket
              : key
                ? familyPlanning?.currentUsersByMethod[key]
                : undefined;

            return (
              <tr key={i} className={isTotal ? 'bg-yellow-50 font-semibold' : ''}>
                <Td className={`${m.startsWith('a.') || m.startsWith('b.') ? 'pl-8' : 'pl-4'}`}>{m}</Td>
                {[0, 1, 2, 3, 4].map(j => (
                  <React.Fragment key={j}>
                    {j === 3 ? (
                      <>
                        <InputCell value={counts?.['10-14']} />
                        <InputCell value={counts?.['15-19']} />
                        <InputCell value={counts?.['20-49']} />
                        <InputCell value={counts?.total} />
                      </>
                    ) : (
                      <>
                        <InputCell /><InputCell /><InputCell /><InputCell />
                      </>
                    )}
                  </React.Fragment>
                ))}
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
};

// ─── SECTION B: Maternal Care ─────────────────────────────────────────────────
// ─── Section B data helpers ──────────────────────────────────────────────────
// Sums several prenatal/postpartum age-bracket buckets together (used for the
// "=(a+b+c)" subtotal rows).
const sumAgeBrackets = (
  data: Record<string, AgeBrackets> | undefined,
  keys: string[],
): AgeBrackets | undefined => {
  if (!data) return undefined;
  const result: AgeBrackets = { '10-14': 0, '15-19': 0, '20-49': 0, total: 0 };
  let found = false;
  keys.forEach((k) => {
    const b = data[k];
    if (b) {
      found = true;
      result['10-14'] += b['10-14'];
      result['15-19'] += b['15-19'];
      result['20-49'] += b['20-49'];
      result.total += b.total;
    }
  });
  return found ? result : undefined;
};

// Same idea, but for the sex-bracket intrapartum buckets.
const sumSexBrackets = (
  data: Record<string, SexBrackets> | undefined,
  keys: string[],
): SexBrackets | undefined => {
  if (!data) return undefined;
  const result: SexBrackets = { male: 0, female: 0, total: 0 };
  let found = false;
  keys.forEach((k) => {
    const b = data[k];
    if (b) {
      found = true;
      result.male += b.male;
      result.female += b.female;
      result.total += b.total;
    }
  });
  return found ? result : undefined;
};

// Renders a prenatal/postpartum row's 4 numeric InputCells from an AgeBrackets bucket.
const AgeInputsFromData = ({ data }: { data?: AgeBrackets }) => (
  <>
    <InputCell value={data?.['10-14']} />
    <InputCell value={data?.['15-19']} />
    <InputCell value={data?.['20-49']} />
    <InputCell value={data?.total} />
  </>
);

// Intrapartum rows are broken down by newborn sex (male/female/total), but the
// table's column headers are age-bracket shaped (10-14 | 15-19 | 20-49 | TOTAL).
// We slot male → col1, female → col2, leave col3 blank, total → col4 so the
// figures still land under a sensible header, without redesigning the table.
const SexInputsAsFour = ({ data }: { data?: SexBrackets }) => (
  <>
    <InputCell value={data?.male} />
    <InputCell value={data?.female} />
    <InputCell />
    <InputCell value={data?.total} />
  </>
);

const SectionB = ({ maternalCare }: { maternalCare?: MaternalCareData }) => {
  const prenatal = maternalCare?.prenatal;
  const intrapartum = maternalCare?.intrapartum;
  const postpartum = maternalCare?.postpartum;

  // Index-aligned with `leftIndicators` / `rightIndicators` below. `undefined`
  // means the sub-indicator can't be derived from the current schema (e.g.
  // Resident vs. TRANS-IN splits) and is left for manual entry.
  const leftValueKeys: (string | undefined)[] = [
    undefined, undefined, 'anc8Completed', undefined, undefined, undefined, undefined, undefined, undefined,
    'nutritionAssessed', 'nutritionNormal', 'nutritionLow', 'nutritionHigh',
    undefined, 'td2PlusFirstPregnancy', 'td2Plus',
  ];
  const rightValueKeys: (string | undefined)[] = [
    undefined, 'ifaCompleted', 'mmCompleted', 'ccCompleted',
    undefined, 'anemiaScreened', 'anemiaDiagnosed',
    undefined, 'gdmScreened', 'gdmDiagnosed',
    undefined, 'dewormed',
    undefined, 'bpMeasured', 'highBpOrDanger', 'referred',
  ];

  // Intrapartum: entries can be a single key, a list of keys to sum (for the
  // "=(a+b+c)" subtotal rows), or undefined.
  const intraLeftValueKeys: (string | string[] | undefined)[] = [
    'totalDeliveries',
    ['attendantPhysician', 'attendantNurse', 'attendantMidwife'],
    'attendantPhysician', 'attendantNurse', 'attendantMidwife',
    ['facilityPublic', 'facilityPrivate'],
    'facilityPublic', 'facilityPrivate',
    ['deliveryVaginal', 'deliveryCesarean', 'deliveryCombined'],
    'deliveryVaginal', 'deliveryCesarean', 'deliveryCombined',
  ];
  const intraRightValueKeys: (string | string[] | undefined)[] = [
    ['outcomeFullTerm', 'outcomePreTerm', 'outcomeFetalDeath'],
    'outcomeFullTerm', 'outcomePreTerm', 'outcomeFetalDeath', 'outcomeAbortion',
    ['birthWeightNormal', 'birthWeightLow', 'birthWeightUnknown'],
    'birthWeightNormal', 'birthWeightLow', 'birthWeightUnknown',
  ];

  const pncLeftValueKeys: (string | undefined)[] = [
    undefined, 'pnc4Completed', undefined, undefined, undefined, undefined, undefined, undefined,
  ];
  const pncRightValueKeys: (string | undefined)[] = [
    undefined, 'ifaCompleted', 'vitACompleted',
    undefined, 'bpMeasured', 'highBpOrDanger', 'referred',
  ];

  const leftIndicators: [string, number][] = [
    ['PRENATAL CARE SERVICES', 0],
    ['1. 8ANC', 1],
    ['1a. No. of Women who delivered and completed at least 8ANC =(a1+a2)', 2],
    ['a1. No. of Women who delivered and Provided 1st to 8th ANC on schedule (Resident)', 3],
    ['a2. No. of Women who delivered and completed at least 8ANC TRANS-IN from other LGUs', 3],
    ['1b. No. of Women who delivered and who were tracked during pregnancy =(b1+b2)', 2],
    ['b1. No. of Women who delivered and who were tracked during pregnancy (Resident)', 3],
    ['b2. No. of TRANS-IN from other LGUs', 3],
    ['b3. No. of TRANS-OUT (with MOV) before completing 8ANC', 3],
    ['2. No. of pregnant women assessed for nutritional status during the first trimester', 1],
    ['2a. Normal BMI', 2],
    ['2b. Low BMI', 2],
    ['2c. High BMI', 2],
    ['3. Tetanus diphtheria (Td) Containing Vaccination Status', 1],
    ['3a. Number of women pregnant for the first time given at least 2 doses of Td vaccination', 2],
    ['3b. Number of Pregnant Women for the 2nd or more times given at least 3 doses of Td vaccination (Td2 Plus)', 2],
  ];
  const rightIndicators: [string, number][] = [
    ['4. Prenatal Supplementation', 1],
    ['4a. Number of pregnant women who completed the dose of Iron with Folic Acid supplementation', 2],
    ['4b. No. of pregnant women who completed the dose Multiple Micronutrient Supplementation', 2],
    ['4c. No. of pregnant women who completed the dose of Calcium carbonate', 2],
    ['5. Anemia Screening', 1],
    ['9a. No of pregnant women screened for Anemia', 2],
    ['9b. No of pregnant women diagnosed with Anemia', 2],
    ['6. Gestational Diabetes Screening', 1],
    ['6a. No of pregnant women screened for Gestational Diabetes Melitus', 2],
    ['6b. No of pregnant women tested positive for Gestational Diabetes Melitus', 2],
    ['7. Deworming', 1],
    ['5a. No. of pregnant women given one dose of deworming tablet', 2],
    ['8. BP measurement', 1],
    ['8a. No. of pregnant women who had their BP measured during each of their antenatal care visit', 2],
    ['8b1. No. of pregnant women identified with high BP or danger signs', 2],
    ['8b2. No. of pregnant women with high BP or danger signs who were referred to a higher-level facility', 2],
  ];

  const indentClass = (n: number) => ['font-bold', 'pl-2', 'pl-4', 'pl-6'][Math.min(n, 3)];

  const intraLeft: [string, number][] = [
    ['1. Total Deliveries', 1],
    ['2. No. of deliveries attended by Skilled Health Professionals (SHP) =(2a+2b+2c)', 1],
    ['2a. Physicians', 2], ['2b. Nurses', 2], ['2c. Midwives', 2],
    ['3. No. of Facility Based Deliveries (FBD) =(3a+3b)', 1],
    ['3a. Public facility', 2], ['3b. Private facility', 2],
    ['4. Delivery by Type =(4a+4b+4c)', 1],
    ['4a. No. of Vaginal deliveries', 2], ['4b. No. of Cesarean Section', 2],
    ['4c. No. of Combined Vaginal-Cesarean deliveries', 2],
  ];
  const intraRight: [string, number][] = [
    ['5. Delivery by Outcome =(5a+5b+5c)', 1],
    ['5a. No. of Full-Term deliveries', 2], ['5b. No. of Pre-Term deliveries', 2],
    ['5c. No. of Fetal deaths', 2], ['5d. No. of abortion/miscarriage (counts only)', 2],
    ['6. No. of Livebirths by birth weight =(6a+6b+6c) — Male | Female | TOTAL', 1],
    ['6b. Normal birth weight', 2], ['6c. Low birth weight', 2], ['6d. Unknown birth weight', 2],
  ];
  const pncLeft: [string, number][] = [
    ['1. 4PNC', 1],
    ['1a. Total No. of women who delivered and completed at least 4PNC =(a1+a2)', 2],
    ['a1. No. of women who delivered and provided 1st to 4th PNC on schedule (Resident)', 3],
    ['a2. No. of women delivered and completed at least 4PNC TRANS IN from other LGUs', 3],
    ['1b. Total No. of women due for PNC =(b1+b2)', 2],
    ['b1. No. of women due for PNC (Resident)', 3],
    ['b2. No. of TRANS-IN from other LGUs due for PNC', 3],
    ['b3. No. of TRANS-OUT (with MOV) before completing 4PNC', 3],
  ];
  const pncRight: [string, number][] = [
    ['2. Postpartum Supplementation', 1],
    ['2a. Number of postpartum women who completed the dose of Iron with Folic Acid Supplementation', 2],
    ['2b. Number of postpartum women who completed the dose of Vitamin A supplementation', 2],
    ['2. BP measurement', 1],
    ['1a. No. of postpartum women who had their BP measured during each of their postnatal care visit', 2],
    ['2b1. No. of postpartum women identified with high BP or danger signs', 2],
    ['2b2. No. of postpartum women with high BP or danger signs who were referred to a higher-level facility', 2],
  ];

  const maxLen = Math.max(leftIndicators.length, rightIndicators.length);
  const maxIntra = Math.max(intraLeft.length, intraRight.length);
  const maxPnc = Math.max(pncLeft.length, pncRight.length);

  return (
    <div className="mb-6">
      <table className="w-full border-collapse text-xs">
        <tbody>
          {/* Table B: 12 Columns */}
          <SectionHeader colSpan={12}>SECTION B. MATERNAL CARE AND SERVICES</SectionHeader>
          
          <tr className="bg-gray-100">
            <Th className="text-left w-1/4">Indicators</Th>
            <Th>10-14</Th><Th>15-19</Th><Th>20-49</Th><Th>TOTAL</Th><Th>Remarks</Th>
            <Th className="text-left w-1/4">Indicators</Th>
            <Th>10-14</Th><Th>15-19</Th><Th>20-49</Th><Th>TOTAL</Th><Th>Remarks</Th>
          </tr>
          {Array.from({ length: maxLen }).map((_, i) => {
            const [lLabel, lIndent] = leftIndicators[i] ?? ['', 1];
            const [rLabel, rIndent] = rightIndicators[i] ?? ['', 1];
            const isLSection = lIndent === 0;
            const lData = prenatal?.[leftValueKeys[i] ?? ''];
            const rData = prenatal?.[rightValueKeys[i] ?? ''];
            return (
              <tr key={i} className={isLSection ? 'bg-blue-50' : ''}>
                <Td className={`w-1/4 ${indentClass(lIndent)} ${isLSection ? 'font-bold' : ''}`}>{lLabel}</Td>
                {isLSection ? <td colSpan={5} className="border border-gray-400"></td> :
                  <><AgeInputsFromData data={lData} /><InputCell /></>}
                <Td className={`w-1/4 ${indentClass(rIndent)}`}>{rLabel}</Td>
                {rLabel ? <><AgeInputsFromData data={rData} /><InputCell /></> :
                  <td colSpan={5} className="border border-gray-400"></td>}
              </tr>
            );
          })}

          {/* Intrapartum */}
          <tr className="bg-gray-100">
            <Th className="text-left" colSpan={6}>INTRAPARTUM AND NEWBORN CARE — Indicators / 10-14 / 15-19 / 20-49 / TOTAL / Remarks</Th>
            <Th className="text-left" colSpan={6}>Indicators / 10-14 | 15-19 | 20-49 | TOTAL | Remarks</Th>
          </tr>
          {Array.from({ length: maxIntra }).map((_, i) => {
            const [lLabel, lIndent] = intraLeft[i] ?? ['', 1];
            const [rLabel, rIndent] = intraRight[i] ?? ['', 1];
            const lKeys = intraLeftValueKeys[i];
            const rKeys = intraRightValueKeys[i];
            const lData = Array.isArray(lKeys) ? sumSexBrackets(intrapartum, lKeys) : intrapartum?.[lKeys ?? ''];
            const rData = Array.isArray(rKeys) ? sumSexBrackets(intrapartum, rKeys) : intrapartum?.[rKeys ?? ''];
            return (
              <tr key={i}>
                <Td className={`w-1/4 ${indentClass(lIndent)}`}>{lLabel}</Td>
                {lLabel ? <><SexInputsAsFour data={lData} /><InputCell /></> :
                  <td colSpan={5} className="border border-gray-400"></td>}
                <Td className={`w-1/4 ${indentClass(rIndent)}`}>{rLabel}</Td>
                {rLabel ? <><SexInputsAsFour data={rData} /><InputCell /></> :
                  <td colSpan={5} className="border border-gray-400"></td>}
              </tr>
            );
          })}

          {/* PNC */}
          <SubSectionHeader colSpan={12}>POSTPARTUM CARE</SubSectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-1/4">Indicators</Th>
            <Th>10-14</Th><Th>15-19</Th><Th>20-49</Th><Th>TOTAL</Th><Th>Remarks</Th>
            <Th className="text-left w-1/4">Indicators</Th>
            <Th>10-14</Th><Th>15-19</Th><Th>20-49</Th><Th>TOTAL</Th><Th>Remarks</Th>
          </tr>
          {Array.from({ length: maxPnc }).map((_, i) => {
            const [lLabel, lIndent] = pncLeft[i] ?? ['', 1];
            const [rLabel, rIndent] = pncRight[i] ?? ['', 1];
            const lData = postpartum?.[pncLeftValueKeys[i] ?? ''];
            const rData = postpartum?.[pncRightValueKeys[i] ?? ''];
            return (
              <tr key={i}>
                <Td className={`w-1/4 ${indentClass(lIndent)}`}>{lLabel}</Td>
                {lLabel ? <><AgeInputsFromData data={lData} /><InputCell /></> :
                  <td colSpan={5} className="border border-gray-400"></td>}
                <Td className={`w-1/4 ${indentClass(rIndent)}`}>{rLabel}</Td>
                {rLabel ? <><AgeInputsFromData data={rData} /><InputCell /></> :
                  <td colSpan={5} className="border border-gray-400"></td>}
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
};

// ─── SECTION C: Child Care ────────────────────────────────────────────────────
// Renders a row's 3 numeric InputCells (Male | Female | Total) from a SexBrackets bucket.
const SexInputsFromData = ({ data }: { data?: SexBrackets }) => (
  <>
    <InputCell value={data?.male} />
    <InputCell value={data?.female} />
    <InputCell value={data?.total} />
  </>
);

const SectionC = ({ childCare }: { childCare?: ChildCareData }) => {
  const imm0_11: [string, number, string][] = [
    ['1. Children protected at birth (CPAB)', 1, ''],
    ['2. BCG (within 24 hours)', 1, '10. OPV 2'],
    ['3. BCG (more than 24 hours to 11 months and 29 days)', 1, '11. OPV 3'],
    ['4. Hep B antigen within 24 hrs after birth', 1, '12. IPV 1'],
    ['5. Hep B antigen more than 24 hrs up to 14 days', 1, '13. IPV 2'],
    ['6. DPT-HiB-HepB 1', 1, '14. PCV 1'],
    ['7. DPT-HiB-HepB 2', 1, '15. PCV 2'],
    ['8. DPT-HiB-HepB 3', 1, '16. PCV 3'],
    ['9. OPV 1', 1, '17. MMR 1'],
  ];
  // Index-aligned with `imm0_11` above. `child_immunization_records` cohort =
  // children whose dateOfBirth falls in the current calendar year.
  const imm0_11LeftKeys: (string | undefined)[] = [
    'cpab', 'bcg24h', 'bcgLate', 'hepB24h', 'hepBLate', 'dpt1', 'dpt2', 'dpt3', 'opv1',
  ];
  const imm0_11RightKeys: (string | undefined)[] = [
    undefined, 'opv2', 'opv3', 'ipv1', 'ipv2', 'pcv1', 'pcv2', 'pcv3', 'mmr1',
  ];

  const imm_prev: [string, string][] = [
    ['1. DPT-HiB-HepB 1', '9. PCV 1'],
    ['2. DPT-HiB-HepB 2', '10. PCV 2'],
    ['3. DPT-HiB-HepB 3', '11. PCV 3'],
    ['4. OPV 1', '12. MMR 1'],
    ['5. OPV 2', '13. MMR 2'],
    ['6. OPV 3', '14. FIC'],
    ['7. IPV 1', '15. CIC'],
    ['8. IPV 2', ''],
  ];
  // Same child_immunization_records fields, but for the cohort born the
  // previous calendar year (catching up on the same vaccination series).
  const immPrevLeftKeys: (string | undefined)[] = [
    'dpt1', 'dpt2', 'dpt3', 'opv1', 'opv2', 'opv3', 'ipv1', 'ipv2',
  ];
  const immPrevRightKeys: (string | undefined)[] = [
    'pcv1', 'pcv2', 'pcv3', 'mmr1', 'mmr2', 'fic', 'cic', undefined,
  ];

  const schoolImm: [string, string][] = [
    ['1. Grade 1 learners given Td', '5. HPV 1 (SBI)'],
    ['2. Grade 1 learners given MR', '6. HPV 1 (CBI)'],
    ['3. Grade 7 learners given Td', '7. HPV 2 (CBI)'],
    ['4. Grade 7 learners given MR', ''],
  ];
  const schoolImmLeftKeys: (string | undefined)[] = ['grade1Td', 'grade1Mr', 'grade7Td', 'grade7Mr'];
  const schoolImmRightKeys: (string | undefined)[] = ['hpv1Sbi', 'hpv1Cbi', 'hpv2Cbi', undefined];

  const nutrition: [string, string][] = [
    ['1. Newborns who were initiated on breastfeeding within 1 hour after birth', '4a. Infants aged 6-11 months old who completed routine MNP supplementation'],
    ['2. Infants born with low birth weight (LBW) given complete Iron supplements', '4b. Children aged 12-23 months old who completed routine MNP supplementation'],
    ['3a. Infants aged 6-11 months old who received 1 dose of Vitamin A supplementation', '5a. Infants aged 6-11 months old who completed routine LNS-SQ supplementation'],
    ['3b. Children aged 12-59 months old who completed 2 doses of Vitamin A Supplementation', '5b. Children aged 12-23 months old who completed routine LNS-SQ supplementation'],
  ];
  const nutritionLeftKeys: (string | undefined)[] = ['breastfeedingInit', 'lbwIronComplete', 'vitA6to11', 'vitA12to59TwoDoses'];
  const nutritionRightKeys: (string | undefined)[] = ['mnp6to11', 'mnp12to23', 'lns6to11', 'lns12to23'];

  const nutrition2: [string, string][] = [
    ['6. Children 0-59 months old SEEN during the reporting period at health facilities', '7d. Died'],
    ['6a. Identified MAM', '8. SAM without complication admitted to OTC'],
    ['6b. Identified SAM', '8a. Cured'],
    ['7. MAM enrolled to SFP', '8b. Non-cured'],
    ['7a. Cured', '8c. Defaulted'],
    ['7b. Non-cured', '8d. Died'],
    ['7c. Defaulted', ''],
  ];
  const nutrition2LeftKeys: (string | undefined)[] = [
    'seen0to59', 'mamIdentified', 'samIdentified', 'mamEnrolled', 'mamCured', 'mamNonCured', 'mamDefaulted',
  ];
  const nutrition2RightKeys: (string | undefined)[] = [
    'mamDied', 'samAdmitted', 'samCured', 'samNonCured', 'samDefaulted', 'samDied', undefined,
  ];

  const mgmtSick: [string, string][] = [
    ['1. Sick infants aged 6-11 months old seen', '4. Pneumonia cases 0-59 months old seen'],
    ['1a. Sick infants aged 6-11 months old who received Vitamin A capsule aside from routine supplementation', '4a. 0-59 months old with pneumonia who received antibiotic treatment'],
    ['2. Sick infants aged 12-59 months old seen', 'a. Amoxicillin drops suspension'],
    ['2a. Sick infants aged 12-59 months old who received Vitamin A capsule aside from routine supplementation', 'b. Amoxicillin-clavulanate suspension'],
    ['3. Acute diarrhea cases 0-59 months old seen', 'c. Cefuroxime suspension'],
    ['3a. 0-59 months old with acute diarrhea who received ORS only', 'd. Other antibiotics'],
    ['3b. 0-59 months old with acute diarrhea who received ORS and Zinc drops/syrup', ''],
  ];
  const mgmtSickLeftKeys: (string | undefined)[] = [
    'sick6to11Seen', 'vitA6to11Sick', 'sick12to59Seen', 'vitA12to59Sick', 'diarrhea0to59Seen', 'orsOnly', 'orsZinc',
  ];
  const mgmtSickRightKeys: (string | undefined)[] = [
    'pneumonia0to59Seen', 'antibioticAny', 'amoxDrops', 'amoxClav', 'cefuroxime', 'otherAntibiotic', undefined,
  ];

  const TwoColSexTable = ({
    rows, dataset, leftKeys, rightKeys,
  }: {
    rows: [string, string][];
    dataset?: Record<string, SexBrackets>;
    leftKeys?: (string | undefined)[];
    rightKeys?: (string | undefined)[];
  }) => (
    <>
      {rows.map(([l, r], i) => (
        <tr key={i}>
          <Td className="pl-4 w-1/3">{l}</Td>
          <SexInputsFromData data={dataset?.[leftKeys?.[i] ?? '']} /><InputCell />
          <Td className="pl-4 w-1/3">{r}</Td>
          {r ? <><SexInputsFromData data={dataset?.[rightKeys?.[i] ?? '']} /><InputCell /></> : <td colSpan={4} className="border border-gray-400"></td>}
        </tr>
      ))}
    </>
  );

  return (
    <div className="mb-6">
      {/* Table C: 10 Columns */}
      <table className="w-full border-collapse text-xs">
        <tbody>
          <SectionHeader colSpan={10}>SECTION C. CHILD CARE AND SERVICES</SectionHeader>

          <SubSectionHeader colSpan={10}>IMMUNIZATION</SubSectionHeader>
          <SubSectionHeader colSpan={10}>A.1. Immunization Services (0-11 months old current year)</SubSectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-1/3">Indicators</Th>
            <Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
            <Th className="text-left w-1/3">Indicators</Th>
            <Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
          </tr>
          {imm0_11.map(([l, , r], i) => (
            <tr key={i}>
              <Td className="pl-4 w-1/3">{l}</Td>
              <SexInputsFromData data={childCare?.imm0_11?.[imm0_11LeftKeys[i] ?? '']} /><InputCell />
              <Td className="pl-4 w-1/3">{r}</Td>
              {r ? <><SexInputsFromData data={childCare?.imm0_11?.[imm0_11RightKeys[i] ?? '']} /><InputCell /></> : <td colSpan={4} className="border border-gray-400"></td>}
            </tr>
          ))}

          <SubSectionHeader colSpan={10}>A.2. Immunization Services (0-11 months of previous year)</SubSectionHeader>
          <TwoColSexTable rows={imm_prev} dataset={childCare?.immPrev} leftKeys={immPrevLeftKeys} rightKeys={immPrevRightKeys} />

          <SubSectionHeader colSpan={10}>A.3. School and Community-Based Immunization</SubSectionHeader>
          <TwoColSexTable rows={schoolImm} dataset={childCare?.schoolImm} leftKeys={schoolImmLeftKeys} rightKeys={schoolImmRightKeys} />

          <SubSectionHeader colSpan={10}>NUTRITION</SubSectionHeader>
          <TwoColSexTable rows={nutrition} dataset={childCare?.nutrition} leftKeys={nutritionLeftKeys} rightKeys={nutritionRightKeys} />
          <TwoColSexTable rows={nutrition2} dataset={childCare?.nutrition2} leftKeys={nutrition2LeftKeys} rightKeys={nutrition2RightKeys} />

          <SubSectionHeader colSpan={10}>MANAGEMENT OF SICK</SubSectionHeader>
          <TwoColSexTable rows={mgmtSick} dataset={childCare?.mgmtSick} leftKeys={mgmtSickLeftKeys} rightKeys={mgmtSickRightKeys} />
        </tbody>
      </table>
    </div>
  );
};

// ─── SECTION D: Oral Health ───────────────────────────────────────────────────
const SectionD = () => {
  const firstVisitLeft: [string, number][] = [
    ['FIRST VISIT TO AN ORAL HEALTH CARE PROFESSIONAL', 0],
    ['1. Infants 0-11 months old who had their first dental visit', 1],
    ['1. Children 1-4 years old who had their 1st visit to an oral health care professional within a year', 1],
    ['1a. Children 1-4 years old who had their 1st visit to a facility-based oral health care professional within a year', 2],
    ['1b. Children 1-4 years old who had their 1st visit to a non-facility-based oral health care professional within a year', 2],
    ['2. Children 5-9 years old who had their 1st visit to an oral health care professional within a year', 1],
    ['2a. Children 5-9 years old who had their 1st visit to a facility-based oral health care professional within a year', 2],
    ['2b. Children 5-9 years old who had their 1st visit to a non-facility-based oral health care professional within a year', 2],
    ['3. Adolescents 10-19 years old who had their 1st visit to an oral health care professional within a year', 1],
    ['3a. Adolescents 10-19 years old who had their 1st visit to a facility-based oral health care professional within a year', 2],
    ['3b. Adolescents 10-19 years old who had their 1st visit to a non-facility-based oral health care professional within a year', 2],
    ['4. Adults 20-59 years old who had their 1st visit to an oral health care professional within a year', 1],
    ['4a. Adults 20-59 years old who had their 1st visit to a facility-based oral health care professional within a year', 2],
    ['4b. Adults 20-59 years old who had their 1st visit to a non-facility-based oral health care professional within a year', 2],
    ['5. Senior Citizens 60 years old and above who had their 1st visit to an oral health care professional within a year', 1],
    ['5a. Senior Citizens 60 years old and above who had their 1st visit to a facility-based oral health care professional within a year', 2],
    ['5b. Senior Citizens 60 years old and above who had their 1st visit to a non-facility-based oral health care professional within a year', 2],
    ['6. Pregnant Women who had their 1st visit to an oral health care professional within a year', 1],
    ['6a. Pregnant Women who had their 1st visit to a facility-based oral health care professional within a year', 2],
    ['6b. Pregnant Women who had their 1st visit to a non-facility-based oral health care professional within a year', 2],
  ];
  const completedLeft: [string, number][] = [
    ['1. Children 1-4 years old who completed 2 visits to an oral health care professional within a year', 1],
    ['1a. Children 1-4 years old who completed 2 visits to a facility-based oral health care professional within a year', 2],
    ['1b. Children 1-4 years old who completed 2 visits to a non-facility-based oral health care professional within a year', 2],
    ['2. Children 5-9 years old who completed 2 visits to an oral health care professional within a year', 1],
    ['2a. Children 5-9 years old who completed 2 visits to a facility-based oral health care professional within a year', 2],
    ['2b. Children 5-9 years old who completed 2 visits to a non-facility-based oral health care professional within a year', 2],
    ['3. Adolescents 10-19 years old who completed 2 visits to an oral health care professional within a year', 1],
    ['3a. Adolescents 10-19 years old who completed 2 visits to a facility-based oral health care professional within a year', 2],
    ['3b. Adolescents 10-19 years old who completed 2 visits to a non-facility-based oral health care professional within a year', 2],
    ['4. Adults 20-59 years old who completed 2 visits to an oral health care professional within a year', 1],
    ['4a. Adults 20-59 years old who completed 2 visits to a facility-based oral health care professional within a year', 2],
    ['4b. Adults 20-59 years old who completed 2 visits to a non-facility-based oral health care professional within a year', 2],
    ['5. Senior Citizens 60 years old and above who completed 2 visits to an oral health care professional within a year', 1],
    ['5a. Senior Citizens 60 years old and above who completed 2 visits to a facility-based oral health care professional within a year', 2],
    ['5b. Senior Citizens 60 years old and above who completed 2 visits to a non-facility-based oral health care professional within a year', 2],
    ['6. Pregnant Women who completed 2 visits to an oral health care professional within a year', 1],
    ['6a. Pregnant Women who completed 2 visits to a facility-based oral health care professional within a year', 2],
    ['6b. Pregnant Women who completed 2 visits to a non-facility-based oral health care professional within a year', 2],
  ];
  const indentClass = (n: number) => ['font-bold', 'pl-2', 'pl-4'][Math.min(n, 2)];
  const maxLen = Math.max(firstVisitLeft.length, completedLeft.length);

  return (
    <div className="mb-6">
      {/* Table D: 10 Columns */}
      <table className="w-full border-collapse text-xs">
        <tbody>
          <SectionHeader colSpan={10}>SECTION D. ORAL HEALTH CARE SERVICES</SectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">Indicators (1st Visit)</Th>
            <Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
            <Th className="text-left w-5/12">Indicators (Completed 2 Visits)</Th>
            <Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
          </tr>
          {Array.from({ length: maxLen }).map((_, i) => {
            const [lLabel, lIndent] = firstVisitLeft[i] ?? ['', 1];
            const [rLabel, rIndent] = completedLeft[i] ?? ['', 1];
            return (
              <tr key={i} className={lIndent === 0 ? 'bg-blue-50' : ''}>
                <Td className={`w-5/12 ${indentClass(lIndent)}`}>{lLabel}</Td>
                {lIndent === 0 ? <td colSpan={4} className="border border-gray-400"></td> :
                  <><SexInputs /><InputCell /></>}
                <Td className={`w-5/12 ${indentClass(rIndent ?? 1)}`}>{rLabel}</Td>
                {rLabel ? <><SexInputs /><InputCell /></> :
                  <td colSpan={4} className="border border-gray-400"></td>}
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
};

// ─── SECTION E: Non-Communicable Diseases ─────────────────────────────────────
const SectionE = () => {
  const lifestyle = [
    '1a. Current Smokers', 'a. Tobacco Products', 'b. Vaporized Nicotine Products', 'c. Both',
    '1b. Provided Brief Tobacco Intervention', '1c. Binge Drinker', '1d. Insufficient physical activities',
    '1e. Consumed unhealthy diet', '1f. Overweight', '1g. Obese',
  ];
  const lifestyle2 = [
    '2a. Current Smokers', 'a. Tobacco Products', 'b. Vaporized Nicotine Products', 'c. Both',
    '2b. Provided Brief Tobacco Intervention', '2c. Binge Drinker', '2d. Insufficient physical activities',
    '2e. Consumed unhealthy diet', '2f. Overweight', '2g. Obese',
  ];

  const cvdRows: [string, string][] = [
    ['1. Adults 20-59 years old who were identified as hypertensive using the PhilPEN protocol', '1. Adults 20-59 years old who were identified with Type II Diabetes using the PhilPEN protocol'],
    ['2. Hypertensives 20-59 years old provided with antihypertensive medications', '2. Type II Diabetics 20-59 years old provided with antidiabetic medications'],
    ['2a. Provided by facility (100%)', '2a. Provided by facility (100%)'],
    ['2b. Out of pocket', '2b. Out of pocket'],
    ['2c. Both', '2c. Both'],
    ['3. Senior Citizens 60 years old and above who were identified as hypertensive using the PhilPEN protocol', '3. Senior Citizens 60 years old and above who were identified with Type II Diabetes using the PhilPEN protocol'],
    ['4. Hypertensives 60 years old and above provided with antihypertensive medications', '4. Type II Diabetics 60 years old and above provided with antidiabetic medications'],
    ['4a. Provided by facility (100%)', '4a. Provided by facility (100%)'],
    ['4b. Out of pocket', '4b. Out of pocket'],
    ['4c. Both', '4c. Both'],
  ];

  const eyeLeft = [
    '1. Screened for eye disease/s', '1a. 0-9 years old screened for eye disease/s',
    '1b. 10-19 years old screened for eye disease/s', '1c. 20-59 years old screened for eye disease/s',
    '1d. 60 years old and above screened for eye disease/s',
    '2. Screened and identified with eye disease/s',
    '2a. 0-9 years old screened and identified with at least one eye ailment',
    '2a1. Changes in vision', '2a2. Changes in appearance', '2a3. Eye and orbital injury', '2a4. Routine eye exams',
    '2b. 10-19 years old screened and identified with at least one eye ailment',
    '2b1. Changes in vision', '2b2. Changes in appearance', '2b3. Eye and orbital injury', '2b4. Routine eye exams',
    '3. Identified with eye disease/s and referred to an eye health professional',
    '3a. 0-9 years old identified with eye disease/s and referred to an eye health professional',
    '3b. 10-19 years old identified with eye disease/s and referred to an eye health professional',
    '3c. 20-59 years old identified with eye disease/s and referred to an eye health professional',
    '3d. Senior Citizens identified with eye disease/s and referred to an eye health professional',
  ];
  const eyeRight = [
    '2c. 20-59 years old screened and identified with at least one eye ailment',
    '2c1. Changes in vision', '2c2. Changes in appearance', '2c3. Eye and orbital injury', '2c4. Routine eye exams',
    '2d. 60 years old and above screened and identified with at least one eye ailment',
    '2d1. Changes in vision', '2d2. Changes in appearance', '2d3. Eye and orbital injury', '2d4. Routine eye exams',
    'E5. Immunization for Senior Citizens',
    '1. Senior Citizens Seen who had not previously received PPV upon reaching 60 years old',
    '2. Senior citizens aged 60 years old and above who received one (1) dose of Pneumococcal Polysaccharide Vaccine',
    '3. Senior Citizens Seen',
    '4. Senior citizens aged 60 years old and above who received one (1) dose of Influenza Vaccine',
    'E6. Geriatric Screening',
    'a. Senior Citizens screened using the geriatric screening tool',
    'b. Senior Citizens with a positive geriatric screening result',
    'b1. Memory', 'b2. Depression', 'b3. Polypharmacy', 'b4. Urinary Incontinence',
  ];

  const cervicalLeft = [
    '1. Women aged 30-65 years old screened or assessed for cervical cancer',
    '1a. VIA', '2a. PapSmear', '3a. HPV DNA', '4a. Assessed Only',
    '2. Women aged 30-65 years old found suspicious for cervical cancer',
    '3. Women aged 30-65 years old found suspicious for cervical cancer and linked to care',
    '3a. Treated', '3b. Referred',
    '4. Women aged 30-65 years old found positive for precancerous lesions',
    '5. Women aged 30-65 years old found positive for precancerous lesions and linked to care',
    '5a. Treated', '5b. Referred',
  ];
  const breastRight = [
    '1. Number of 30-69 years old women seen',
    '2. Number of high-risk and/or symptomatic women',
    '3. High-risk and/or symptomatic women aged 30-69 years old provided with Breast Cancer Early Detection Services',
    '3a. Clinical Breast Examination', '3b. Mammogram',
    '4. High-risk and/or symptomatic women aged 30-69 years old found with remarkable or significant results',
    '4a. Clinical Breast Examination', '4b. Mammogram',
    '5. High-risk and/or symptomatic women aged 30-69 years old found with remarkable results and linked to care',
    '5a. Clinical Breast Examination', '5b. Mammogram',
    '6. Asymptomatic women aged 50-69 years old screened for breast cancer',
    '6a. Clinical Breast Examination', '6b. Mammogram',
  ];

  const maxEye = Math.max(eyeLeft.length, eyeRight.length);
  const maxCancer = Math.max(cervicalLeft.length, breastRight.length);

  return (
    <div className="mb-6">
      {/* Table E: 10 Columns */}
      <table className="w-full border-collapse text-xs">
        <tbody>
          <SectionHeader colSpan={10}>SECTION E. NON-COMMUNICABLE DISEASES</SectionHeader>

          {/* Lifestyle */}
          <SubSectionHeader colSpan={10}>E1. Lifestyle Related</SubSectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">
              1. Adults 20-59 years old who were risk assessed using the PhilPEN protocol
            </Th>
            <Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
            <Th className="text-left w-5/12">
              2. Senior Citizens 60 years old and above who were risk assessed using the PhilPEN protocol
            </Th>
            <Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
          </tr>
          {lifestyle.map((l, i) => (
            <tr key={i}>
              <Td className="pl-4">{l}</Td><SexInputs /><InputCell />
              <Td className="pl-4">{lifestyle2[i] ?? ''}</Td>
              {lifestyle2[i] ? <><SexInputs /><InputCell /></> : <td colSpan={4} className="border border-gray-400"></td>}
            </tr>
          ))}

          {/* CVD & Diabetes */}
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">E2. Cardiovascular Disease Prevention and Control</Th>
            <Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
            <Th className="text-left w-5/12">E3. Diabetes Mellitus Prevention and Control</Th>
            <Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
          </tr>
          <tr>
            <Td className="pl-4 font-semibold">The total number of identified adult (20-59 years old) hypertensives (Sum of January to Previous Month)</Td>
            <SexInputs /><InputCell />
            <Td className="pl-4 font-semibold">The total number of identified adult (20-59 years old) with Type II Diabetes (Sum of January to Previous Month)</Td>
            <SexInputs /><InputCell />
          </tr>
          <tr>
            <Td className="pl-4">The total number of identified adult (20-59 years old) hypertensives in the current month</Td>
            <SexInputs /><InputCell />
            <Td className="pl-4">The total number of identified adult (20-59 years old) with Type II Diabetes in the current month</Td>
            <SexInputs /><InputCell />
          </tr>
          {cvdRows.map(([l, r], i) => (
            <tr key={i}>
              <Td className="pl-4">{l}</Td><SexInputs /><InputCell />
              <Td className="pl-4">{r}</Td><SexInputs /><InputCell />
            </tr>
          ))}

          {/* Blindness */}
          <SubSectionHeader colSpan={10}>E4. Blindness Prevention Program</SubSectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">Indicators</Th>
            <Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
            <Th className="text-left w-5/12">Indicators</Th>
            <Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
          </tr>
          {Array.from({ length: maxEye }).map((_, i) => {
            const l = eyeLeft[i] ?? '';
            const r = eyeRight[i] ?? '';
            return (
              <tr key={i} className={l.startsWith('E5') || l.startsWith('E6') ? 'bg-blue-50 font-bold' : ''}>
                <Td className="pl-4 w-5/12">{l}</Td>
                {l ? <><SexInputs /><InputCell /></> : <td colSpan={4} className="border border-gray-400"></td>}
                <Td className="pl-4 w-5/12">{r}</Td>
                {r ? <><SexInputs /><InputCell /></> : <td colSpan={4} className="border border-gray-400"></td>}
              </tr>
            );
          })}

          {/* Mental Health */}
          <SubSectionHeader colSpan={10}>E7. Mental Health</SubSectionHeader>
          <tr>
            <Td className="pl-4" colSpan={5}>
              1. Individuals with mental health concern screened using the Mental Health Gap Action Programme (mhGAP)
              <br />
              <span className="text-gray-500 italic">(Age Groups: 0-9 Male/Female | 10-19 Male/Female | 20-59 Male/Female | 60+ Male/Female)</span>
            </Td>
            <td colSpan={5} className="border border-gray-400">
              <div className="grid grid-cols-8 gap-0 h-full">
                {[...Array(8)].map((_, i) => (
                  <input key={i} type="number" className="border-r border-gray-300 text-center text-xs p-1 w-full outline-none" defaultValue="" />
                ))}
              </div>
            </td>
          </tr>

          {/* Cancer */}
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">E8. Cervical Cancer Prevention and Control Services</Th>
            <Th colSpan={4}>Total / Remarks</Th>
            <Th className="text-left w-5/12">E9. Breast Cancer Prevention and Control Services</Th>
            <Th colSpan={4}>Total / Remarks</Th>
          </tr>
          {Array.from({ length: maxCancer }).map((_, i) => {
            const l = cervicalLeft[i] ?? '';
            const r = breastRight[i] ?? '';
            return (
              <tr key={i}>
                <Td className="pl-4 w-5/12">{l}</Td>
                {l ? <><InputCell /><InputCell /></> : <td colSpan={2} className="border border-gray-400"></td>}
                <td colSpan={2} className="border border-gray-400"></td>
                <Td className="pl-4 w-5/12">{r}</Td>
                {r ? <><InputCell /><InputCell /></> : <td colSpan={2} className="border border-gray-400"></td>}
                <td colSpan={2} className="border border-gray-400"></td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
};

// ─── SECTION F: Environmental Health ─────────────────────────────────────────
const SectionF = () => (
  <div className="mb-6">
    {/* Table F: 6 Columns */}
    <table className="w-full border-collapse text-xs">
      <tbody>
        <SectionHeader colSpan={6}>SECTION F. ENVIRONMENTAL HEALTH AND SANITATION</SectionHeader>
        <tr className="bg-gray-100">
          <Th className="text-left w-5/12">G1. Water — Indicators</Th><Th>Total</Th><Th>Remarks</Th>
          <Th className="text-left w-5/12">G1. Sanitation — Indicators</Th><Th>Total</Th><Th>Remarks</Th>
        </tr>
        {([
          ['1. Households (HHs) with access to improved water supply - Total', '1. HH with basic sanitation facility - Total'],
          ['1a. HH with Level I', '1a. HH with pour/flush toilet connected to a septic tank'],
          ['1b. HH with Level II', '1b. HHs with pour/flush toilet connected to community sewer/sewerage system or any other approved treatment system'],
          ['1c. HH with Level III', '1c. HH with Ventilated Improved Pit (VIP) Latrine'],
          ['2. HH using safely managed drinking water service', '2. HH using safely managed sanitation service'],
        ] as [string, string][]).map(([l, r], i) => (
          <tr key={i}>
            <Td className="pl-4 w-5/12">{l}</Td><InputCell /><InputCell />
            <Td className="pl-4 w-5/12">{r}</Td><InputCell /><InputCell />
          </tr>
        ))}
      </tbody>
    </table>
  </div>
);

// ─── SECTION G: Infectious Diseases ──────────────────────────────────────────
const SectionG = () => {
  const filiariasisLeft = [
    '1. No. of individual examined for lymphatic filariasis',
    '1a. Nocturnal Blood Examination (NBE)', '1b. Rapid Diagnostic Test (RDT)',
    '1c. Total no. of individuals examined for lymphedema through NBE and RDT',
    '2. No. of individual found positive for lymphatic filariasis',
    '2a. Nocturnal Blood Examination (NBE)', '2b. Rapid Diagnostic Test (RDT)',
    '2c. Total no. of individuals found positive for lymphedema through NBE and RDT',
    '3. Lymphedema', '3a. 2-4 years old', '3b. 5-14 years old', '3c. 15 years old and above',
    '3d. Total no. of individuals aged 2 yrs old and above examined for the 1st time with lymphedema',
    '4. Elephentiasis', '4a. 2-4 years old', '4b. 5-14 years old', '4c. 15 years old and above',
    '4d. Total no. of individuals aged 2 yrs old and above examined for the 1st time with Elephentiasis',
  ];
  const filiariasisRight = [
    '3. Hydrocele', '3a. 2-4 years old', '3b. 5-14 years old', '3c. 15 years old and above',
    '3d. Total no. of individuals aged 2 yrs old and above examined for the 1st time with Hydrocele',
    '4. Number of individuals who received Mass Drug Administration',
    '4a. 2-4 years old', '4b. 5-14 years old', '4c. 15 years old and above',
    '4d. Total no. of individuals aged 2 yrs old and above who received MDA',
  ];

  const schLeft = [
    '1. Patients Seen', '1a. 1-4 years old', '1b. 5-14 yrs old', '1c. 15-19 yrs old', '1d. 20-59 yrs old', '1e. 60 yrs old and above',
    '2. Clinical/Suspected Schistosomiasis Cases Seen',
    '2a. 1-4 years old', '2b. 5-14 yrs old', '2c. 15-19 yrs old', '2d. 20-59 yrs old', '2e. 60 yrs old and above',
    '3. Clinical/Suspected Schistosomiasis Cases Treated by age group',
    '3a. 5-14 years old', '3a. 15-19 years old', '3a. 20-59 years old', '3a. 60 years old and above',
    '4. Clinical/Suspected Schistosomiasis Cases Treated by treatment type',
    '4a. 1st treatment', '4b. Retreatment',
    '5. Clinical/Suspected Schistosomiasis Cases Cured',
    '5a. 5-14 years old', '5b. 15-19 years old', '5c. 20-59 years old', '5d. 60 years old and above',
    '6. Confirmed COMPLICATED Schistosomiasis Cases by age group',
    '6a. 1-4 years old', '6b. 5-14 years old', '6c. 15-19 years old', '6d. 20-59 years old', '6e. 60 years old and above',
    '7. Confirmed NON-COMPLICATED Schistosomiasis Cases by age group',
    '7a. 1-4 years old', '7b. 5-14 years old', '7c. 15-19 years old', '7d. 20-59 years old', '7e. 60 years old and above',
  ];
  const schRight = [
    '8. Confirmed COMPLICATED Schistosomiasis Cases TREATED',
    '8a. 5-14 years old', '8b. 15-19 years old', '8c. 20-59 years old', '8d. 60 years old and above',
    '9. Confirmed NON-COMPLICATED Schistosomiasis Cases TREATED',
    '9a. 5-14 years old', '9b. 15-19 years old', '9c. 20-59 years old', '9d. 60 years old and above',
    '10. Confirmed Schistosomiasis Cases Treated by treatment',
    '10a. 1st treatment', '10b. Retreatment',
    '11. Confirmed COMPLICATED Schistosomiasis Cases CURED by age group',
    '11a. 5-14 years old', '11b. 15-19 years old', '11c. 20-59 years old', '11d. 60 years old and above',
    '12. Confirmed NON-COMPLICATED Schistosomiasis Cases CURED by age group',
    '12a. 5-14 years old', '12b. 15-19 years old', '12c. 20-59 years old', '12d. 60 years old and above',
    '13. Confirmed Schistosomiasis Cases Referred to Hospital Facility',
    '13a. 1-4 years old', '13b. 5-14 yrs old', '13c. 15-19 yrs old', '13d. 20-59 yrs old', '13e. 60 yrs old and above',
    '14. Individuals dewormed with one (1) dose of Praziquantel during MDA',
    '14a. 5-14 yrs old', '14b. 15-19 yrs old', '14c. 20-59 yrs old', '14d. 60 yrs old and above',
  ];

  const sthLeft = [
    '1. Screened for STH', '1a. 1-4 years old', '1b. 5-14 years old', '1c. 15-19 years old', '1d. 20-59 years old', '1e. 60 years old and above',
    '2. Suspected Cases of STH by place of diagnosis', '2a. Resident', '2b. Non-Resident',
    '3. Suspected Cases of STH by age group',
    '3a. 1-4 years old', '3b. 5-14 years old', '3c. 15-19 years old', '3d. 20-59 years old', '3e. 60 years old and above',
    '4. Confirmed STH Cases by place of diagnosis', '4a. Resident', '4b. Non-Resident',
    '5. Confirmed STH Cases by age group',
    '5a. 1-4 years old', '5b. 5-14 years old', '5c. 15-19 years old', '5d. 20-59 years old', '5e. 60 years old and above',
  ];
  const sthRight = [
    '6. Confirmed STH Cases Treated by place of diagnosis', '6a. Resident', '6b. Non-Resident',
    '7. Confirmed STH Cases Treated by age group',
    '7a. 1-4 years old', '7b. 5-14 years old', '7c. 15-19 years old', '7d. 20-59 years old', '7e. 60 years old and above',
    '8. 1-4 years old who were dewormed during January MDA',
    '8a. School-Based deworming services', '8b. Community Based services',
    '9. 1-4 years old who were dewormed during July MDA',
    '9a. School-Based deworming services', '9b. Community Based services',
    '10. 5-14 years old who were dewormed during January MDA',
    '10a. School-Based deworming services', '10b. Community Based services',
    '11. 5-14 years old who were dewormed during July MDA',
    '11a. School-Based deworming services', '11b. Community Based services',
    '12. 15-19 yrs old who were dewormed during January/July MDA',
    '12a. No. of adolescents (15-19 yrs old) who were dewormed during January MDA',
    '12b. No. of adolescents (15-19 yrs old) who were dewormed during July MDA',
  ];

  const lepLeft = [
    '1. No. of registered Leprosy cases', '1a. 0-14 years old', '1b. 15-18 years old', '1c. 19 years old and above',
    '2. No. of newly detected case', '2a. 0-14 years old', '2b. 15-18 years old', '2c. 19 years old and above',
    '3. Confirmed Leprosy Cases', '3a. 0-14 years old', '3b. 15-18 years old', '3c. 19 years old and above',
  ];
  const lepRight = [
    '4. Completed fixed duration Multi-Drug Therapy (MDT)', '4a. 0-14 years old', '4b. 15-18 years old', '4c. 19 years old and above',
    '5. No. of confirmed leprosy cases treated', '5a. 0-14 years old', '5b. 15-18 years old', '5c. 19 years old and above',
    '6. Newly Detected Cases with Grade 2 Disabilities', '6a. 0-14 years old', '6b. 15-18 years old', '6c. 19 years old and above',
  ];

  const hivRows: [string, string][] = [
    ['1. Pregnant women screened for syphilis - Total', '5. Pregnant women screened reactive for HIV - Total'],
    ['1a. 10-14 years old', '5a. 10-14 years old'], ['1b. 15-19 years old', '5b. 15-19 years old'], ['1c. 20-49 years old', '5c. 20-49 years old'],
    ['2. Pregnant women screened reactive for syphilis - Total', '6. Pregnant women screened for Hepatitis B - Total'],
    ['2a. 10-14 years old', '6a. 10-14 years old'], ['2b. 15-19 years old', '6b. 15-19 years old'], ['2c. 20-49 years old', '6c. 20-49 years old'],
    ['3. Pregnant women treated for syphilis - Total', '7. Pregnant women screened reactive for Hepatitis B - Total'],
    ['3a. 10-14 years old', '7a. 10-14 years old'], ['3b. 15-19 years old', '7b. 15-19 years old'], ['3c. 20-49 years old', '7c. 20-49 years old'],
    ['4. Pregnant women screened for HIV - Total', ''],
    ['4a. 10-14 years old', ''], ['4b. 15-19 years old', ''], ['4c. 20-49 years old', ''],
  ];

  const maxFil = Math.max(filiariasisLeft.length, filiariasisRight.length);
  const maxSch = Math.max(schLeft.length, schRight.length);
  const maxSth = Math.max(sthLeft.length, sthRight.length);
  const maxLep = Math.max(lepLeft.length, lepRight.length);

  return (
    <div className="mb-6">
      {/* Table G: 10 Columns */}
      <table className="w-full border-collapse text-xs">
        <tbody>
          <SectionHeader colSpan={10}>SECTION G. INFECTIOUS DISEASE PREVENTION AND CONTROL SERVICES</SectionHeader>

          {/* Filariasis */}
          <SubSectionHeader colSpan={10}>A. Filariasis</SubSectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">Indicators</Th><Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
            <Th className="text-left w-5/12">Indicators</Th><Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
          </tr>
          {Array.from({ length: maxFil }).map((_, i) => (
            <tr key={i}>
              <Td className="pl-4 w-5/12">{filiariasisLeft[i] ?? ''}</Td>
              {filiariasisLeft[i] ? <><SexInputs /><InputCell /></> : <td colSpan={4} className="border border-gray-400"></td>}
              <Td className="pl-4 w-5/12">{filiariasisRight[i] ?? ''}</Td>
              {filiariasisRight[i] ? <><SexInputs /><InputCell /></> : <td colSpan={4} className="border border-gray-400"></td>}
            </tr>
          ))}

          {/* Rabies */}
          <SubSectionHeader colSpan={10}>B. Rabies</SubSectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">1. Animal Bites — No. of Animal Bites</Th><Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
            <Th className="text-left w-5/12">2. Rabies Death — No. of Rabies Death</Th><Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
          </tr>
          <tr>
            <Td className="pl-4"></Td><SexInputs /><InputCell />
            <Td className="pl-4"></Td><SexInputs /><InputCell />
          </tr>

          {/* Schistosomiasis */}
          <SubSectionHeader colSpan={10}>C. Schistosomiasis</SubSectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">Indicators</Th><Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
            <Th className="text-left w-5/12">Indicators</Th><Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
          </tr>
          {Array.from({ length: maxSch }).map((_, i) => (
            <tr key={i}>
              <Td className="pl-4 w-5/12">{schLeft[i] ?? ''}</Td>
              {schLeft[i] ? <><SexInputs /><InputCell /></> : <td colSpan={4} className="border border-gray-400"></td>}
              <Td className="pl-4 w-5/12">{schRight[i] ?? ''}</Td>
              {schRight[i] ? <><SexInputs /><InputCell /></> : <td colSpan={4} className="border border-gray-400"></td>}
            </tr>
          ))}

          {/* STH */}
          <SubSectionHeader colSpan={10}>D. Soil-Transmitted Helminthiasis</SubSectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">Indicators</Th><Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
            <Th className="text-left w-5/12">Indicators</Th><Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
          </tr>
          {Array.from({ length: maxSth }).map((_, i) => (
            <tr key={i}>
              <Td className="pl-4 w-5/12">{sthLeft[i] ?? ''}</Td>
              {sthLeft[i] ? <><SexInputs /><InputCell /></> : <td colSpan={4} className="border border-gray-400"></td>}
              <Td className="pl-4 w-5/12">{sthRight[i] ?? ''}</Td>
              {sthRight[i] ? <><SexInputs /><InputCell /></> : <td colSpan={4} className="border border-gray-400"></td>}
            </tr>
          ))}

          {/* Leprosy */}
          <SubSectionHeader colSpan={10}>E. Leprosy</SubSectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">Indicators</Th><Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
            <Th className="text-left w-5/12">Indicators</Th><Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
          </tr>
          {Array.from({ length: maxLep }).map((_, i) => (
            <tr key={i}>
              <Td className="pl-4 w-5/12">{lepLeft[i] ?? ''}</Td>
              {lepLeft[i] ? <><SexInputs /><InputCell /></> : <td colSpan={4} className="border border-gray-400"></td>}
              <Td className="pl-4 w-5/12">{lepRight[i] ?? ''}</Td>
              {lepRight[i] ? <><SexInputs /><InputCell /></> : <td colSpan={4} className="border border-gray-400"></td>}
            </tr>
          ))}

          {/* HIV-AIDS/STI */}
          <SubSectionHeader colSpan={10}>F. HIV-AIDS/STI</SubSectionHeader>
          <tr className="bg-gray-100">
            <Th className="text-left w-5/12">Indicators</Th><Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
            <Th className="text-left w-5/12">Indicators</Th><Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
          </tr>
          {hivRows.map(([l, r], i) => (
            <tr key={i}>
              <Td className="pl-4 w-5/12">{l}</Td>
              {l ? <><SexInputs /><InputCell /></> : <td colSpan={4} className="border border-gray-400"></td>}
              <Td className="pl-4 w-5/12">{r}</Td>
              {r ? <><SexInputs /><InputCell /></> : <td colSpan={4} className="border border-gray-400"></td>}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

// ─── SECTION H: Vital Statistics ─────────────────────────────────────────────
const SectionH = () => (
  <div className="mb-6">
    {/* Table H: 11 Columns */}
    <table className="w-full border-collapse text-xs">
      <tbody>
        <SectionHeader colSpan={11}>SECTION H. VITAL STATISTICS</SectionHeader>
        <tr className="bg-gray-100">
          <Th className="text-left w-5/12">Part I. Mortality — Indicators</Th>
          <Th>10-14</Th><Th>15-19</Th><Th>20-49</Th><Th>TOTAL</Th><Th>Remarks</Th>
          <Th className="text-left w-5/12">Part II. Natality — Indicators</Th>
          <Th>Male</Th><Th>Female</Th><Th>Total</Th><Th>Remarks</Th>
        </tr>
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
            {l ? <><AgeInputs /><InputCell /></> : <td colSpan={5} className="border border-gray-400"></td>}
            <Td className="pl-4 w-5/12">{r}</Td>
            {r ? <><SexInputs /><InputCell /></> : <td colSpan={4} className="border border-gray-400"></td>}
          </tr>
        ))}
        <tr className="bg-gray-100">
          <Th className="text-left" colSpan={6}>2. Infant Mortality (Male | Female | Total)</Th>
          <Th colSpan={5}>&nbsp;</Th>
        </tr>
        <tr>
          <Td className="pl-4">2. Infant Mortality</Td>
          <SexInputs />
          <InputCell />
          <td className="border border-gray-400"></td> {/* Fix: Replaced colSpan={2} which overextended the left side to 7 cols */}
          <td colSpan={5} className="border border-gray-400"></td>
        </tr>
      </tbody>
    </table>
  </div>
);

// ─── MAIN COMPONENT ───────────────────────────────────────────────────────────
export default function M1AllPrograms({ familyPlanning, maternalCare, childCare }: M1AllProgramsProps) {
  const [activeSection, setActiveSection] = useState<string>('all');

  const sections = [
    { id: 'all', label: 'All' },
    { id: 'a', label: 'A. Family Planning' },
    { id: 'b', label: 'B. Maternal Care' },
    { id: 'c', label: 'C. Child Care' },
    { id: 'd', label: 'D. Oral Health' },
    { id: 'e', label: 'E. NCDs' },
    { id: 'f', label: 'F. Env. Health' },
    { id: 'g', label: 'G. Infectious Diseases' },
    { id: 'h', label: 'H. Vital Statistics' },
  ];

  const show = (id: string) => activeSection === 'all' || activeSection === id;

  return (
    <div className="bg-white shadow-sm rounded-lg border border-gray-200 p-4">
      {/* Header */}
      <div className="mb-4 flex flex-wrap justify-between items-center gap-2">
        <div>
          <h2 className="text-xl font-bold text-gray-800">M1: All Programs</h2>
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
        {sections.map(s => (
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
        {show('a') && <SectionA familyPlanning={familyPlanning} />}
        {show('b') && <SectionB maternalCare={maternalCare} />}
        {show('c') && <SectionC childCare={childCare} />}
        {show('d') && <SectionD />}
        {show('e') && <SectionE />}
        {show('f') && <SectionF />}
        {show('g') && <SectionG />}
        {show('h') && <SectionH />}
      </div>
    </div>
  );
}