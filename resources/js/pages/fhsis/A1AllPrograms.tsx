import React, { useState, useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';
import {
  Th,
  Td,
  InputCell,
  SectionHeader,
  SectionA,
  SectionB,
  SectionC,
  SectionD,
  SectionE,
  SectionF,
  SectionG,
  SectionH,
  type FamilyPlanningData,
  type MaternalCareData,
  type ChildCareData,
  type OralHealthData,
  type NonCommunicableDiseaseData,
  type EnvironmentalHealthData,
  type InfectiousDiseaseData,
  type VitalStatisticsData,
} from './m1Sections';

// Parse JSON-encoded or plain arrays stored on the user model
const parseArray = (val: unknown): string[] => {
  if (Array.isArray(val)) return val as string[];
  if (typeof val === 'string') {
    try { const p = JSON.parse(val); return Array.isArray(p) ? p : []; } catch { return []; }
  }
  return [];
};

// ─── Location Data Shapes ────────────────────────────────────────────────────
interface Region { regCode: string; regDesc: string; }
interface Province { provCode: string; provDesc: string; regCode: string; }
interface Municipality { citymunCode: string; citymunDesc: string; provCode: string; }
interface Barangay { brgyCode: string; brgyDesc: string; citymunCode: string; }

interface A1AllProgramsProps {
  regions?: Region[];
  provinces?: Province[];
  municipalities?: Municipality[];
  barangays?: Barangay[];
  onApplyFilter?: (
    month: string,
    year: string,
    location?: {
      region_code: string;
      province_code: string;
      municipality_code: string;
      barangay_codes: string[];
    },
  ) => void;
}

// Live report data, one entry per M1 section (same endpoints as the M1 page).
interface ReportData {
  familyPlanning?: FamilyPlanningData;
  maternalCare?: MaternalCareData;
  childCare?: ChildCareData;
  oralHealth?: OralHealthData;
  nonCommunicableDisease?: NonCommunicableDiseaseData;
  environmentalHealth?: EnvironmentalHealthData;
  infectiousDisease?: InfectiousDiseaseData;
  vitalStatistics?: VitalStatisticsData;
}

const endpoints: [keyof ReportData, string, string][] = [
  ['familyPlanning', 'family-planning', 'Family Planning'],
  ['maternalCare', 'maternal-care', 'Maternal Care'],
  ['childCare', 'child-care', 'Child Care'],
  ['oralHealth', 'oral-health', 'Oral Health'],
  ['nonCommunicableDisease', 'non-communicable-disease', 'Non-Communicable Disease'],
  ['environmentalHealth', 'environmental-health', 'Environmental Health'],
  ['infectiousDisease', 'infectious-disease', 'Infectious Disease'],
  ['vitalStatistics', 'vital-statistics', 'Vital Statistics'],
];

// A1 is an annual report. The submit workflow still stores a month, so the
// annual period is recorded against December of the selected year.
const ANNUAL_PERIOD_MONTH = '12';

// Sex column helpers (used by the Facility & Workforce table)
const SexHeaders = () => (
  <>
    <Th>Male</Th>
    <Th>Female</Th>
    <Th>Total</Th>
  </>
);
const SexInputs = ({ totalVal = '' }: { totalVal?: string }) => (
  <>
    <InputCell />
    <InputCell />
    <InputCell value={totalVal} />
  </>
);

// ─── SECTION: Health Facility & Workforce Data ───────────────────────────────
const SectionFacility = ({ data }: { data?: any }) => {
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
  barangays = [],
  onApplyFilter,
}: A1AllProgramsProps) {
  const { auth } = usePage<SharedData>().props;
  const user = auth?.user as any;

  // Administrators and DOH can change all location filters freely.
  // Public Health Nurses can change barangay but not region/province/municipality.
  // BHS (Midwife) users can only pick among the barangays assigned to them
  // (region/province/municipality stay locked, barangay list is restricted).
  // All other roles have every location field locked.
  const isBHS = user?.role === 'BHS';
  const isLocationLocked = !['Administrator', 'DOH'].includes(user?.role ?? '');
  const isBarangayLocked = !['Administrator', 'DOH', 'Public Health Nurse', 'BHS'].includes(user?.role ?? '');
  const assignedBarangayCodes = useMemo(() => parseArray(user?.barangay_codes), [user?.barangay_codes]);

  const [activeSection, setActiveSection] = useState<string>('all');

  // Form State — year defaults to current; location pre-filled from logged-in user
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear().toString());
  const [selectedRegion, setSelectedRegion] = useState(user?.region_code ?? '');
  const [selectedProvince, setSelectedProvince] = useState(user?.province_code ?? '');
  const [selectedMunicipality, setSelectedMunicipality] = useState(user?.municipality_code ?? '');
  // Barangay is multi-select — user may be assigned several codes
  const [selectedBarangays, setSelectedBarangays] = useState<string[]>(parseArray(user?.barangay_codes));

  const [reportData, setReportData] = useState<ReportData>({});
  const [facilityData, setFacilityData] = useState<any>(undefined);
  const [isLoading, setIsLoading] = useState(false);
  const [hasLoaded, setHasLoaded] = useState(false);
  const [errors, setErrors] = useState<string[]>([]);

  // ─── Cascading Location Filters ──────────────────────────────────────────
  const filteredProvinces = useMemo(
    () => provinces.filter((p) => p.regCode === selectedRegion),
    [selectedRegion, provinces],
  );
  const filteredMunicipalities = useMemo(
    () => municipalities.filter((m) => m.provCode === selectedProvince),
    [selectedProvince, municipalities],
  );
  const filteredBarangays = useMemo(() => {
    const inMunicipality = barangays.filter((b) => b.citymunCode === selectedMunicipality);
    // BHS users only see the barangays assigned to them.
    return isBHS
      ? inMunicipality.filter((b) => assignedBarangayCodes.includes(b.brgyCode))
      : inMunicipality;
  }, [selectedMunicipality, barangays, isBHS, assignedBarangayCodes]);

  // BHS users with nothing ticked fall back to ALL their assigned barangays,
  // so an empty selection never widens the report to the whole municipality.
  const effectiveBarangays = isBHS && selectedBarangays.length === 0
    ? assignedBarangayCodes
    : selectedBarangays;

  // ─── Filter -> API Query Builder (yearly: the whole selected year) ───────
  const buildReportQuery = () => {
    const params = new URLSearchParams();
    params.set('annual', '1');
    params.set('year', selectedYear || String(new Date().getFullYear()));
    if (selectedRegion) params.set('region', selectedRegion);
    if (selectedProvince) params.set('province', selectedProvince);
    if (selectedMunicipality) params.set('municipality', selectedMunicipality);
    effectiveBarangays.forEach((code) => params.append('barangay[]', code));
    return params.toString();
  };

  // ─── Apply Filters -> fetch every report tied to a live endpoint ─────────
  const applyFilters = async () => {
    const year = selectedYear || String(new Date().getFullYear());
    const query = buildReportQuery();

    onApplyFilter?.(ANNUAL_PERIOD_MONTH, year, {
      region_code: selectedRegion,
      province_code: selectedProvince,
      municipality_code: selectedMunicipality,
      barangay_codes: effectiveBarangays,
    });

    setIsLoading(true);
    setErrors([]);

    const failures: string[] = [];
    await Promise.all([
      ...endpoints.map(async ([key, path, label]) => {
        try {
          const res = await fetch(`/qfhsis/public/api/${path}/report?${query}`);
          if (!res.ok) throw new Error(`Request failed with status ${res.status}`);
          const json = await res.json();
          setReportData((prev) => ({ ...prev, [key]: json.data }));
        } catch (err) {
          failures.push(`${label}: ${err instanceof Error ? err.message : 'failed to load'}`);
        }
      }),
      // Facility & Workforce data
      (async () => {
        try {
          const res = await fetch(`/qfhsis/public/api/reports/filtered-m1-all?${query}`);
          if (!res.ok) throw new Error(`Request failed with status ${res.status}`);
          const json = await res.json();
          setFacilityData(json.data || json);
        } catch (err) {
          console.warn('Facility & Workforce data failed to load', err);
        }
      })(),
    ]);

    setErrors(failures);
    setHasLoaded(true);
    setIsLoading(false);
  };

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
    { id: 'facility', label: 'Facility & Workforce' },
  ];

  const show = (id: string) => activeSection === 'all' || activeSection === id;

  const DynamicFormHeader = () => (
    <div className="mb-6 p-4 bg-slate-50 rounded-lg border border-slate-200 grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">

      {/* Col 1: Time Parameters (yearly — no month) */}
      <div className="space-y-2">
        <label className="block font-semibold text-gray-700">Reporting Year</label>
        <input
          type="text"
          className="w-full border border-gray-300 rounded px-2 py-1.5 bg-white outline-none focus:border-blue-500 text-center"
          value={selectedYear}
          onChange={(e) => setSelectedYear(e.target.value)}
          placeholder="YYYY"
        />
      </div>

      {/* Col 2: Region & Province Nodes */}
      <div className="space-y-2">
        <label className="block font-semibold text-gray-700">Region</label>
        <select
          className={`w-full border border-gray-300 rounded px-2 py-1.5 outline-none focus:border-blue-500 ${isLocationLocked ? 'opacity-60 cursor-not-allowed bg-gray-50' : 'bg-white'}`}
          value={selectedRegion}
          disabled={isLocationLocked}
          onChange={(e) => {
            setSelectedRegion(e.target.value);
            setSelectedProvince('');
            setSelectedMunicipality('');
            setSelectedBarangays([]);
          }}
        >
          <option value="">Select Region</option>
          {regions.map((r) => <option key={r.regCode} value={r.regCode}>{r.regDesc}</option>)}
        </select>

        <label className="block font-semibold text-gray-700 mt-2">Province</label>
        <select
          className={`w-full border border-gray-300 rounded px-2 py-1.5 outline-none focus:border-blue-500 ${isLocationLocked || !selectedRegion ? 'opacity-60 cursor-not-allowed bg-gray-50' : 'bg-white'}`}
          value={selectedProvince}
          disabled={isLocationLocked || !selectedRegion}
          onChange={(e) => {
            setSelectedProvince(e.target.value);
            setSelectedMunicipality('');
            setSelectedBarangays([]);
          }}
        >
          <option value="">Select Province</option>
          {filteredProvinces.map((p) => <option key={p.provCode} value={p.provCode}>{p.provDesc}</option>)}
        </select>
      </div>

      {/* Col 3: Municipality & Barangay Leaf Nodes */}
      <div className="space-y-2">
        <label className="block font-semibold text-gray-700">Municipality / City</label>
        <select
          className={`w-full border border-gray-300 rounded px-2 py-1.5 outline-none focus:border-blue-500 ${isLocationLocked || !selectedProvince ? 'opacity-60 cursor-not-allowed bg-gray-50' : 'bg-white'}`}
          value={selectedMunicipality}
          disabled={isLocationLocked || !selectedProvince}
          onChange={(e) => {
            setSelectedMunicipality(e.target.value);
            setSelectedBarangays([]);
          }}
        >
          <option value="">Select Municipality</option>
          {filteredMunicipalities.map((m) => <option key={m.citymunCode} value={m.citymunCode}>{m.citymunDesc}</option>)}
        </select>

        <div className="flex items-center justify-between mt-2">
          <label className="block font-semibold text-gray-700">
            Barangay
            <span className="ml-2 text-[10px] font-normal text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded-full">
              {selectedBarangays.length} selected
            </span>
          </label>
          {/* All / Clear only shown to users who can change location */}
          {!isBarangayLocked && filteredBarangays.length > 0 && (
            <div className="flex gap-2 text-[10px] font-medium">
              <button
                type="button"
                onClick={() => setSelectedBarangays(filteredBarangays.map((b) => b.brgyCode))}
                className="text-blue-600 hover:text-blue-800 transition"
              >
                All
              </button>
              <button
                type="button"
                onClick={() => setSelectedBarangays([])}
                className="text-gray-400 hover:text-gray-700 transition"
              >
                Clear
              </button>
            </div>
          )}
        </div>
        <div className={`w-full border border-gray-300 rounded max-h-28 overflow-y-auto space-y-0.5 p-1 transition ${!selectedMunicipality || isBarangayLocked ? 'opacity-60 pointer-events-none bg-gray-50' : 'bg-white'}`}>
          {filteredBarangays.length === 0 ? (
            <p className="text-[11px] text-gray-400 italic px-2 py-1">
              {selectedMunicipality ? 'No barangays found.' : 'Select a municipality first…'}
            </p>
          ) : (
            filteredBarangays.map((b) => (
              <label key={b.brgyCode} className={`flex items-center gap-2 px-2 py-1 rounded select-none ${isBarangayLocked ? 'cursor-not-allowed' : 'hover:bg-blue-50 cursor-pointer'}`}>
                <input
                  type="checkbox"
                  value={b.brgyCode}
                  checked={selectedBarangays.includes(b.brgyCode)}
                  disabled={isBarangayLocked}
                  onChange={(e) => {
                    setSelectedBarangays((prev) =>
                      e.target.checked
                        ? [...prev, b.brgyCode]
                        : prev.filter((c) => c !== b.brgyCode),
                    );
                  }}
                  className="w-3.5 h-3.5 text-blue-600 border-gray-300 rounded focus:ring-blue-500 disabled:cursor-not-allowed"
                />
                <span className="text-[11px] text-gray-700">{b.brgyDesc}</span>
              </label>
            ))
          )}
        </div>
      </div>

      {/* Apply Filters */}
      <div className="md:col-span-3 flex flex-wrap items-center gap-3 pt-1 border-t border-slate-200 mt-1">
        <button
          type="button"
          onClick={applyFilters}
          disabled={isLoading}
          className="bg-blue-600 text-white px-4 py-1.5 rounded hover:bg-blue-700 transition text-xs font-semibold disabled:opacity-60 disabled:cursor-not-allowed"
        >
          {isLoading ? 'Loading Report…' : 'Apply Filters'}
        </button>
        {errors.length > 0 && (
          <span className="text-red-600 text-xs font-medium">{errors.join(' · ')}</span>
        )}
        {errors.length === 0 && !isLoading && hasLoaded && (
          <span className="text-gray-500 text-xs">
            Showing annual data for {selectedYear}
            {selectedBarangays.length > 0
              ? ` · ${selectedBarangays.length === 1
                  ? filteredBarangays.find((b) => b.brgyCode === selectedBarangays[0])?.brgyDesc ?? selectedBarangays[0]
                  : `${selectedBarangays.length} barangays`}`
              : selectedMunicipality && filteredMunicipalities.find((m) => m.citymunCode === selectedMunicipality)
                ? ` · ${filteredMunicipalities.find((m) => m.citymunCode === selectedMunicipality)?.citymunDesc}`
                : ''}
          </span>
        )}
      </div>
    </div>
  );

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

      <DynamicFormHeader />

      {/* Sections */}
      <div className="overflow-x-auto space-y-2">
        {show('a') && <SectionA familyPlanning={reportData.familyPlanning} />}
        {show('b') && <SectionB maternalCare={reportData.maternalCare} />}
        {show('c') && <SectionC childCare={reportData.childCare} />}
        {show('d') && <SectionD oralHealth={reportData.oralHealth} />}
        {show('e') && <SectionE nonCommunicableDisease={reportData.nonCommunicableDisease} />}
        {show('f') && <SectionF environmentalHealth={reportData.environmentalHealth} />}
        {show('g') && <SectionG infectiousDisease={reportData.infectiousDisease} />}
        {show('h') && <SectionH vitalStatistics={reportData.vitalStatistics} />}
        {show('facility') && <SectionFacility data={facilityData} />}
      </div>
    </div>
  );
}
