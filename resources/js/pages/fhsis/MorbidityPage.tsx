import { Fragment } from 'react';
import { Head } from '@inertiajs/react';
// Adjust this import to match your project's layout, e.g.:
// import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

/* ------------------------------------------------------------------ */
/*  Types                                                              */
/* ------------------------------------------------------------------ */

type AgeGroupKey =
  | 'days_0_6'
  | 'days_7_28'
  | 'days_29_11mo'
  | 'years_1_4'
  | 'years_5_9'
  | 'years_10_14'
  | 'years_15_19'
  | 'years_20_24'
  | 'years_25_29'
  | 'years_30_34'
  | 'years_35_39'
  | 'years_40_44'
  | 'years_45_49'
  | 'years_50_54'
  | 'years_55_59'
  | 'years_60_up';

interface AgeGroupDef {
  key: AgeGroupKey;
  label: string;
}

/** The 16 reportable age brackets, left to right, as they appear on the DOH form. */
const AGE_GROUPS: AgeGroupDef[] = [
  { key: 'days_0_6', label: '0-6 days' },
  { key: 'days_7_28', label: '7-28 days' },
  { key: 'days_29_11mo', label: '29 days to 11 months' },
  { key: 'years_1_4', label: '1-4 years old' },
  { key: 'years_5_9', label: '5-9 years old' },
  { key: 'years_10_14', label: '10-14 years old' },
  { key: 'years_15_19', label: '15-19 years old' },
  { key: 'years_20_24', label: '20-24 years old' },
  { key: 'years_25_29', label: '25-29 years old' },
  { key: 'years_30_34', label: '30-34 years old' },
  { key: 'years_35_39', label: '35-39 years old' },
  { key: 'years_40_44', label: '40-44 years old' },
  { key: 'years_45_49', label: '45-49 years old' },
  { key: 'years_50_54', label: '50-54 years old' },
  { key: 'years_55_59', label: '55-59 years old' },
  { key: 'years_60_up', label: '60 years and above' },
];

/** Male/Female case count for one disease row, in one age bracket. */
interface SexCount {
  male: number;
  female: number;
}

/** All age-bracket counts for a single disease row. Missing brackets default to 0/0. */
type MorbidityRowData = Partial<Record<AgeGroupKey, SexCount>>;

/**
 * Report data keyed by `"<icd>::<disease name>"` (a couple of ICD codes such as
 * A09.0 repeat across two rows in the source form, so ICD code alone isn't a unique key).
 */
export type MorbidityReportData = Record<string, MorbidityRowData>;

export function morbidityRowKey(icd: string, name: string): string {
  return `${icd}::${name}`;
}

interface MorbidityPageProps {
  data?: MorbidityReportData;
  facilityName?: string;
  /** e.g. "January 2026" */
  reportingPeriod?: string;
}

/* ------------------------------------------------------------------ */
/*  Static form structure (from M2_Morbidity.xlsx, DOH FHSIS           */
/*  Section A.1. Morbidity Report — 20 ICD-10 chapters, 306 rows)      */
/* ------------------------------------------------------------------ */

export interface MorbidityDisease {
  name: string;
  icd: string;
}

export interface MorbiditySection {
  title: string;
  icdRange: string;
  diseases: MorbidityDisease[];
}

export const MORBIDITY_SECTIONS: MorbiditySection[] = [
  {
    title: 'Certain Infectious and Parasitic Diseases',
    icdRange: 'A00-B99',
    diseases: [
      { name: 'Cholera', icd: 'A00' },
      { name: 'Typhoid and paratyphoid fevers', icd: 'A01' },
      { name: 'Shigellosis', icd: 'A03' },
      { name: 'Amoebiasis', icd: 'A06' },
      { name: 'Diarrhea and gastroenteritis of presumed infectious origin', icd: 'A09' },
      { name: 'Other and unspecified gastroenteritis and colitis of infectious origin; acute bloody diarrhea', icd: 'A09.0' },
      { name: 'Other and unspecified gastroenteritis and colitis of infectious origin; acute watery diarrhea', icd: 'A09.0' },
      { name: 'Other intestinal infectious diseases', icd: 'A02, A04-A05, A07-A08' },
      { name: 'Respiratory tuberculosis', icd: 'A15-A16' },
      { name: 'Other tuberculosis', icd: 'A17-Al9' },
      { name: 'Plague', icd: 'A20' },
      { name: 'Brucellosis', icd: 'A23' },
      { name: 'Leptospirosis', icd: 'A27' },
      { name: 'Leprosy', icd: 'A30' },
      { name: 'Tetanus neonatorum', icd: 'A33' },
      { name: 'Other tetanus', icd: 'A34-A35' },
      { name: 'Diphtheria', icd: 'A36' },
      { name: 'Whooping cough', icd: 'A37' },
      { name: 'Meningococcal infection', icd: 'A39' },
      { name: 'Sepsis', icd: 'A40-A41' },
      { name: 'Other bacterial diseases', icd: 'A2l -A22, A24-A28, A3l -A32, A38, A42-A49' },
      { name: 'Congenital syphilis', icd: 'A50' },
      { name: 'Early syphilis', icd: 'A51' },
      { name: 'Other syphilis', icd: 'A52-A53' },
      { name: 'Gonococcal infection', icd: 'A54' },
      { name: 'Sexually transmitted chlamydial diseases', icd: 'A55-A56' },
      { name: 'Other infection with a predominantly sexual mode of transmission', icd: 'A57-A64' },
      { name: 'Yaws', icd: 'A66' },
      { name: 'Relapsing fevers', icd: 'A68' },
      { name: 'Trachoma', icd: 'A71' },
      { name: 'Typhus fever', icd: 'A75' },
      { name: 'Acute poliomyelitis', icd: 'A80' },
      { name: 'Rabies', icd: 'A82' },
      { name: 'Viral encephalitis', icd: 'A83-A86' },
      { name: 'Yellow fever', icd: 'A95' },
      { name: 'Dengue', icd: 'A97' },
      { name: 'Other arthropod-borne viral fever and viral haemorrhagic fevers', icd: 'A92-A94, A96-A99' },
      { name: 'Herpesviral infections', icd: 'B00' },
      { name: 'Varicella and zoster', icd: 'B01-B02' },
      { name: 'Measles', icd: 'B05' },
      { name: 'Rubella', icd: 'B06' },
      { name: 'Acute hepatitis A', icd: 'B15' },
      { name: 'Acute hepatitis B', icd: 'B16' },
      { name: 'Other acute viral hepatitis', icd: 'B17' },
      { name: 'Other chronic and unspecified viral hepatitis', icd: 'B18' },
      { name: 'Human immunodeficiency virus [HIV] disease', icd: 'B20-B24' },
      { name: 'Mumps', icd: 'B26' },
      { name: 'Other viral diseases', icd: 'A81, A87-A89, B03-B04, B07-B09, 825, B27-B34' },
      { name: 'Mycoses', icd: 'B35-B49' },
      { name: 'Malaria', icd: 'B50-B54' },
      { name: 'Leishmaniasis', icd: 'B55' },
      { name: 'Trypanosomiasis', icd: 'B56-B57' },
      { name: 'Schistosomiasis', icd: 'B65' },
      { name: 'Other fluke infections', icd: 'B66' },
      { name: 'Echinococcosis', icd: 'B67' },
      { name: 'Dracunculiasis', icd: 'B72' },
      { name: 'Onchocerciasis', icd: 'B73' },
      { name: 'Filariasis', icd: 'B74' },
      { name: 'Hookworm diseases', icd: 'B76' },
      { name: 'Other helminthiases', icd: 'B68-B71, B75, B77-B83' },
      { name: 'Sequelae of tuberculosis', icd: 'B90' },
      { name: 'Sequelae of poliomyelitis', icd: 'B91' },
      { name: 'Sequelae of leprosy', icd: 'B92' },
      { name: 'Other infectious and parasitic diseases', icd: 'A65-A67, A69-A70, A74, A77-A79, B58-B64, B85-B89, B94, B99' },
    ],
  },
  {
    title: 'Neoplasms',
    icdRange: 'C00-D48',
    diseases: [
      { name: 'Malignant neoplasm of lip, oral cavity and pharynx', icd: 'C00-C14' },
      { name: 'Malignant neoplasm of oesophagus', icd: 'C15' },
      { name: 'Malignant neoplasm of stomach', icd: 'C16' },
      { name: 'Malignant neoplasm of colon', icd: 'C18' },
      { name: 'Malignant neoplasm of rectosigmoid junction, rectum, anus and anal canal', icd: 'C19-C21' },
      { name: 'Malignant neoplasm of liver and intrahepatic bile ducts', icd: 'C22' },
      { name: 'Malignant neoplasm of pancreas', icd: 'C25' },
      { name: 'Other malignant neoplasms of digestive organs', icd: 'C17, C23-C24, C26' },
      { name: 'Malignant neoplasms of larynx', icd: 'C32' },
      { name: 'Malignant neoplasm of trachea, bronchus and lung', icd: 'C33-C34' },
      { name: 'Other malignant neoplasms of respiratory and intrathoracic organs', icd: 'C30-C31, C37-C39' },
      { name: 'Malignant neoplasm of bone and articular cartilage', icd: 'C40-C41' },
      { name: 'Malignant melanoma of skin', icd: 'C43' },
      { name: 'Other malignant neoplasms of skin', icd: 'C44' },
      { name: 'Malignant neoplasms of mesothelial and soft tissue', icd: 'C45-C49' },
      { name: 'Malignant neoplasm of breast', icd: 'C50' },
      { name: 'Malignant neoplasm of cervix uteri', icd: 'C53' },
      { name: 'Malignant neoplasm of other and unspecified parts of uterus', icd: 'C54-C55' },
      { name: 'Other malignant neoplasms of female genital organs', icd: 'C51-C52, C56-C58' },
      { name: 'Malignant neoplasm of prostate', icd: 'C61' },
      { name: 'Other malignant neoplasms of male genital organs', icd: 'C60,C62-C63' },
      { name: 'Malignant neoplasm of bladder', icd: 'C67' },
      { name: 'Other malignant neoplasms of urinary tract', icd: 'C64-C66, C68' },
      { name: 'Malignant neoplasm of eye and adnexa', icd: 'C69' },
      { name: 'Malignant neoplasm of brain', icd: 'C71' },
      { name: 'Malignant neoplasm of other parts of central nervous system', icd: 'C70,C72' },
      { name: 'Malignant neoplasm of other, ill-defined, secondary, unspecified, and multiple sites', icd: 'C73-C80,C97' },
      { name: 'Hodgkin disease', icd: 'C81' },
      { name: 'Non-Hodgkin lymphoma', icd: 'C82-C85' },
      { name: 'Leukaemia', icd: 'C91-C95' },
      { name: 'Other malignant neoplasms of lymphoid, hematopoietic, and related tissue', icd: 'C88-C90,C96' },
      { name: 'Carcinoma in situ of cervix uteri', icd: 'D06' },
      { name: 'Benign neoplasm of skin', icd: 'D22-D23' },
      { name: 'Benign neoplasm of breast', icd: 'D24' },
      { name: 'Leiomyoma of uterus', icd: 'D25' },
      { name: 'Benign neoplasm of ovary', icd: 'D27' },
      { name: 'Benign neoplasm of urinary organs', icd: 'D30' },
      { name: 'Benign neoplasm of brain and other parts of central nervous system', icd: 'D33' },
      { name: 'Other in situ and benign neoplasms and neoplasms of uncertain and unknown behavior', icd: 'D00-D05,D07-D21,D26,D28-D29,D31-D32,D34-D48' },
    ],
  },
  {
    title: 'Diseases of the blood and blood-forming organs and certain disorders involving the immune mechanism',
    icdRange: 'D50-D89',
    diseases: [
      { name: 'Iron deficiency anemia', icd: 'D50' },
      { name: 'Other anemias', icd: 'D51-D64' },
      { name: 'Haemorrhagic conditions and other diseases of blood and blood-forming organs', icd: 'D65-D77' },
      { name: 'Certain disorders involving the immune mechanism', icd: 'D80-D89' },
    ],
  },
  {
    title: 'Endocrine, nutritional and metabolic diseases',
    icdRange: 'E00-E90',
    diseases: [
      { name: 'Iodine-deficiency-related thyroid disorders', icd: 'E00-E02' },
      { name: 'Thyrotoxicosis', icd: 'E05' },
      { name: 'Other disorders of thyroid', icd: 'E03-E04,E06-E07' },
      { name: 'Diabetes mellitus', icd: 'E10-E14' },
      { name: 'Malnutrition', icd: 'E40-E46' },
      { name: 'Vitamin A deficiency', icd: 'E50' },
      { name: 'Other vitamin deficiencies', icd: 'E51-E56' },
      { name: 'Sequelae of malnutrition and other nutritional deficiencies', icd: 'E64' },
      { name: 'Obesity', icd: 'E66' },
      { name: 'Volume depletion', icd: 'E86' },
      { name: 'Other endocrine, nutritional and metabolic disorders', icd: 'E15-E35,E58-E63,E65,E67-E85,E87-E90' },
    ],
  },
  {
    title: 'Mental and Behavioral Disorders',
    icdRange: 'F01-F99',
    diseases: [
      { name: 'Dementia', icd: 'F00-F03' },
      { name: 'Mental and behavioral disorders due to use of alcohol', icd: 'F10' },
      { name: 'Mental and behavioral disorders due to other psychoactive substance use', icd: 'F11-F19' },
      { name: 'Schizophrenia, schizotypal, and delusional disorders', icd: 'F20-F29' },
      { name: 'Mood [affective] disorders', icd: 'F30-F39' },
      { name: 'Neurotic, stress-related, and somatoform disorders', icd: 'F40-F48' },
      { name: 'Mental retardation', icd: 'F70-F79' },
      { name: 'Other mental and behavioural disorders', icd: 'F04-F09,F50-F69,F80-F99' },
    ],
  },
  {
    title: 'Diseases of the nervous system',
    icdRange: 'G00-G98',
    diseases: [
      { name: 'Inflammatory diseases of the central nervous system', icd: 'G00-G09' },
      { name: 'Parkinson disease', icd: 'G20' },
      { name: 'Alzheimer disease', icd: 'G30' },
      { name: 'Multiple sclerosis', icd: 'G35' },
      { name: 'Epilepsy', icd: 'G40-G41' },
      { name: 'Migraine and other headache syndromes', icd: 'G43-G44' },
      { name: 'Transient cerebral ischaemic attacks and related syndromes', icd: 'G45' },
      { name: 'Nerve, nerve root and plexus disorders', icd: 'G50-G59' },
      { name: 'Cerebral palsy and other paralytic syndromes', icd: 'G80-G83' },
      { name: 'Other diseases of the nervous system', icd: 'G10-G14,G21-G26,G31-G32,G36-G37,G46-G47,G60-G73,G90-G99' },
    ],
  },
  {
    title: 'Diseases of the eye and adnexa',
    icdRange: 'H00-H59',
    diseases: [
      { name: 'Inflammation of eyelid', icd: 'H00-H01' },
      { name: 'Conjunctivitis and other disorders of conjunctiva', icd: 'H10-H13' },
      { name: 'Keratitis and other disorders of sclera and cornea', icd: 'H15-H19' },
      { name: 'Cataract and other disorders of lens', icd: 'H25-H28' },
      { name: 'Retinal detachments and breaks', icd: 'H33' },
      { name: 'Glaucoma', icd: 'H40-H42' },
      { name: 'Strablismus', icd: 'H49-H50' },
      { name: 'Disorders of refraction and accommodation', icd: 'H52' },
      { name: 'Blindness and low vision', icd: 'H54' },
      { name: 'Other diseases of the eye and adnexa', icd: 'H02-H06, H20-H22, H30-H32, H34-H36, H43-H48, H51, H53, H55-H59' },
    ],
  },
  {
    title: 'Diseases of the ear and mastoid process',
    icdRange: 'H60-H95',
    diseases: [
      { name: 'Otitis media and other disorders of middle ear and mastoid', icd: 'H65-H75' },
      { name: 'Hearing loss', icd: 'H90-H91' },
      { name: 'Other diseases of the ear and mastoid process', icd: 'H60-H62, H80-H83, H92-H95' },
    ],
  },
  {
    title: 'Diseases of the circulatory system',
    icdRange: 'I00-I99',
    diseases: [
      { name: 'Acute rheumatic fever', icd: 'I00-102' },
      { name: 'Chronic rheumatic heart disease', icd: 'I05-109' },
      { name: 'Essential (primary) hypertension', icd: 'I10' },
      { name: 'Other hypertensive diseases', icd: 'I11-I15' },
      { name: 'Acute myocardial infarction', icd: 'I21-I22' },
      { name: 'Other ischaemic heart diseases', icd: 'I20, I23-I25' },
      { name: 'Pulmonary embolism', icd: 'I26' },
      { name: 'Conduction disorders and cardiac arrhythmias', icd: 'I44-I49' },
      { name: 'Heart failure', icd: 'I50' },
      { name: 'Other heart diseases', icd: 'I27-I43, I51-I52' },
      { name: 'Intracranial haemorrhage', icd: 'I60-I62' },
      { name: 'Cerebral infarction', icd: 'I63' },
      { name: 'Stroke, not specified as haemorrhage or infarction', icd: 'I64' },
      { name: 'Other cerebrovascular diseases', icd: 'I65-I69' },
      { name: 'Atherosclerosis', icd: 'I70' },
      { name: 'Other peripheral vascular diseases', icd: 'I73' },
      { name: 'Arterial embolism and thrombosis', icd: 'I74' },
      { name: 'Other diseases of arteries, arterioles and capillaries', icd: 'I71-I72, I77-I79' },
      { name: 'Phlebitis, thrombophlebitis, venous embolism and thrombosis', icd: 'I80-I82' },
      { name: 'Varicose veins of lower extremities', icd: 'I83' },
      { name: 'Other diseases of the circulatory system', icd: 'I85-I99' },
    ],
  },
  {
    title: 'Diseases of the respiratory system',
    icdRange: 'J00-J99',
    diseases: [
      { name: 'Acute pharyngitis and acute tonsillitis', icd: 'J02–J03' },
      { name: 'Acute laryngitis and tracheitis', icd: 'J04' },
      { name: 'Other acute upper respiratory infections', icd: 'J00–J01, J05–J06' },
      { name: 'Influenza', icd: 'J09–J11' },
      { name: 'Pneumonia', icd: 'J12–J18' },
      { name: 'Acute bronchitis and acute bronchiolitis', icd: 'J20–J21' },
      { name: 'Chronic sinusitis', icd: 'J32' },
      { name: 'Other diseases of nose and nasal sinuses', icd: 'J30–J31, J33–J34' },
      { name: 'Chronic disease of tonsils and adenoids', icd: 'J35' },
      { name: 'Other diseases of upper respiratory tract', icd: 'J36–J39' },
      { name: 'Bronchitis', icd: 'J40-J42' },
      { name: 'Emphysema', icd: 'J43' },
      { name: 'Other chronic obstructive pulmonary diseases', icd: 'J44' },
      { name: 'Asthma', icd: 'J45–J46' },
      { name: 'Bronchiectasis', icd: 'J47' },
      { name: 'Pneumoconiosis', icd: 'J60–J65' },
      { name: 'Unspecified acute lower respiratory infection', icd: 'J22' },
      { name: 'Other diseases of the respiratory system', icd: 'J22, J66–J99' },
    ],
  },
  {
    title: 'Diseases of the digestive system',
    icdRange: 'K00-K93',
    diseases: [
      { name: 'Dental caries', icd: 'K02' },
      { name: 'Other disorders of teeth and supporting structures', icd: 'K00–K01, K03–K08' },
      { name: 'Other diseases of the oral cavity, salivary glands and jaws', icd: 'K09–K14' },
      { name: 'Gastric and duodenal ulcer', icd: 'K25–K27' },
      { name: 'Gastritis and duodenitis', icd: 'K29' },
      { name: 'Other diseases of oesophagus, stomach and duodenum', icd: 'K20–K23, K28, K30–K31' },
      { name: 'Diseases of appendix', icd: 'K35–K38' },
      { name: 'Inguinal hernia', icd: 'K40' },
      { name: 'Other hernia', icd: 'K41–K46' },
      { name: 'Crohn disease and ulcerative colitis', icd: 'K50–K51' },
      { name: 'Paralytic ileus and intestinal obstruction without hernia', icd: 'K56' },
      { name: 'Diverticular disease of intestine', icd: 'K57' },
      { name: 'Other diseases of intestines and peritoneum', icd: 'K52–K55, K58–K67' },
      { name: 'Alcoholic liver disease', icd: 'K70' },
      { name: 'Other diseases of liver', icd: 'K71–K77' },
      { name: 'Cholelithiasis and cholecystitis', icd: 'K80–K81' },
      { name: 'Acute pancreatitis and other diseases of the pancreas', icd: 'K85–K86' },
      { name: 'Hemorrhoids', icd: 'K64' },
      { name: 'Other diseases of the digestive system', icd: 'K82–K83, K87–K93' },
    ],
  },
  {
    title: 'Diseases of the Skin and subcutaneous tissue',
    icdRange: 'L00–L99',
    diseases: [
      { name: 'Infections of the skin and subcutaneous tissue', icd: 'L00–L08' },
      { name: 'Other diseases of the skin and subcutaneous', icd: 'L10–L99' },
    ],
  },
  {
    title: 'Diseases of the musculoskeletal system and connective tissue',
    icdRange: 'M00–M99',
    diseases: [
      { name: 'Rheumatoid arthritis and other inflammatory polyarthropathies', icd: 'M05–M14' },
      { name: 'Arthrosis', icd: 'M15–M19' },
      { name: 'Acquired deformities of limbs', icd: 'M20–M21' },
      { name: 'Other disorders of joints', icd: 'M00–M03, M22–M25' },
      { name: 'Systemic connective tissue disorders', icd: 'M30–M36' },
      { name: 'Cervical and other intervertebral disc disorders', icd: 'M50–M51' },
      { name: 'Other dorsopathies', icd: 'M40–M49, M53–M54' },
      { name: 'Soft tissue disorders', icd: 'M60–M79' },
      { name: 'Disorders of bone density and structure', icd: 'M80–M85' },
      { name: 'Osteomyelitis', icd: 'M86' },
      { name: 'Other diseases of the musculoskeletal system and connective tissue', icd: 'M87–M99' },
    ],
  },
  {
    title: 'Diseases of the genitourinary system',
    icdRange: 'N00–N99',
    diseases: [
      { name: 'Acute and rapidly progressive nephritic syndromes', icd: 'N00–N01' },
      { name: 'Other glomerular diseases', icd: 'N02–N08' },
      { name: 'Renal tubulo–interstitial diseases', icd: 'N10–N16' },
      { name: 'Renal failure', icd: 'N17–N19' },
      { name: 'Urolithiasis', icd: 'N20–N23' },
      { name: 'Cystitis', icd: 'N30' },
      { name: 'Other diseases of the urinary system', icd: 'N25–N29, N31–N39' },
      { name: 'Urinary tract infection, site not specified', icd: 'N39.0' },
      { name: 'Hyperplasia of prostate', icd: 'N40' },
      { name: 'Other disorders of prostate', icd: 'N41–N42' },
      { name: 'Hydrocele and spermatocele', icd: 'N43' },
      { name: 'Redundant prepuce, phimosis and paraphimosis', icd: 'N47' },
      { name: 'Other diseases of male genital organs', icd: 'N44–N46, N48–N51' },
      { name: 'Disorders of the breast', icd: 'N60–N64' },
      { name: 'Salpingitis and oophoritis', icd: 'N70' },
      { name: 'Inflammatory disease of cervix uteri', icd: 'N72' },
      { name: 'Other inflammatory diseases of female pelvic organs', icd: 'N71, N73–N77' },
      { name: 'Endometriosis', icd: 'N80' },
      { name: 'Female genital prolapse', icd: 'N81' },
      { name: 'Noninflammatory disorders of ovary, fallopian tube and broad ligament', icd: 'N83' },
      { name: 'Disorders of menstruation', icd: 'N91–N92' },
      { name: 'Menopausal and other perimenopausal disorders', icd: 'N95' },
      { name: 'Female infertility', icd: 'N97' },
      { name: 'Other disorders of genitourinary tract', icd: 'N82, N84–N90, N93–N94, N96, N98–N99' },
    ],
  },
  {
    title: 'Pregnancy, childbirth and the puerperium',
    icdRange: 'O00–O99',
    diseases: [
      { name: 'Spontaneous abortion', icd: 'O03' },
      { name: 'Medical abortion', icd: 'O04' },
      { name: 'Other pregnancies with abortive outcome', icd: 'O00–O02, O05–O08' },
      { name: 'Oedema, proteinuria and hypertensive disorders in pregnancy, childbirth and the puerperium', icd: 'O10–O16' },
      { name: 'Placenta previa, premature separation of placenta and antepartum haemorrhage', icd: 'O44–O46' },
      { name: 'Other maternal care related to fetus and amniotic cavity and possible delivery problems', icd: 'O30–O43, O47–O48' },
      { name: 'Obstructed labour', icd: 'O64–O66' },
      { name: 'Postpartum haemorrhage', icd: 'O72' },
      { name: 'Other complications of pregnancy and delivery', icd: 'O20–O29, O60–O63, O67–O71, O73–O75, O81–O84' },
      { name: 'Complications predominantly related to the puerperium and other obstetric conditions, not elsewhere classified', icd: 'O85–O99' },
    ],
  },
  {
    title: 'Certain conditions originating in the perinatal period',
    icdRange: 'P00–P96',
    diseases: [
      { name: 'Fetus and newborn affected by maternal factors and by complications of pregnancy, labour and delivery', icd: 'P00–P04' },
      { name: 'Slow fetal growth, fetal malnutrition and disorders related to short gestation and low birth weight', icd: 'P05–P07' },
      { name: 'Birth trauma', icd: 'P10–P15' },
      { name: 'Intrauterine hypoxia and birth asphyxia', icd: 'P20–P21' },
      { name: 'Other respiratory disorders originating in the perinatal period', icd: 'P22–P28' },
      { name: 'Congenital infectious and parasitic diseases', icd: 'P35–P37' },
      { name: 'Other infections specific to the perinatal period', icd: 'P38–P39' },
      { name: 'Haemolytic disease of fetus and newborn', icd: 'P55' },
      { name: 'Other conditions originating in the perinatal period', icd: 'P08, P29, P50–P54, P56–P96' },
    ],
  },
  {
    title: 'Congenital Malformations, Deformations and Chromosomal Abnormalities',
    icdRange: 'Q00-Q99',
    diseases: [
      { name: 'Spina bifida', icd: 'Q05' },
      { name: 'Other congenital malformations of the nervous system', icd: 'Q00–Q04, Q06–Q07' },
      { name: 'Congenital malformations of the circulatory system', icd: 'Q20–Q28' },
      { name: 'Cleft lip and cleft palate', icd: 'Q35–Q37' },
      { name: 'Absence, atresia and stenosis of small intestine', icd: 'Q41' },
      { name: 'Other congenital malformations of the digestive system', icd: 'Q38–Q40, Q42–Q45' },
      { name: 'Undescended testicle', icd: 'Q53' },
      { name: 'Other malformations of the genitourinary system', icd: 'Q50–Q52, Q54–Q64' },
      { name: 'Congenital deformities of hip', icd: 'Q65' },
      { name: 'Congenital deformities of feet', icd: 'Q66' },
      { name: 'Other congenital malformations and deformities of the musculoskeletal system', icd: 'Q67–Q79' },
      { name: 'Other congenital malformations', icd: 'Q10–Q18, Q30–Q34, Q80–Q89' },
      { name: 'Chromosomal abnormalities, not elsewhere classified', icd: 'Q90–Q99' },
    ],
  },
  {
    title: 'Symptoms, Signs and Abnormal Clinical and Laboratory Findings, Not Elsewhere Classified',
    icdRange: 'R00-R99',
    diseases: [
      { name: 'Abdominal and pelvic pain', icd: 'R10' },
      { name: 'Fever of unknown origin', icd: 'R50' },
      { name: 'Senility', icd: 'R54' },
      { name: 'Other symptoms, signs and abnormal clinical and laboratory findings, not elsewhere classified', icd: 'R00–R09, R11–R49, R51–R53, R55–R99' },
    ],
  },
  {
    title: 'Injury, poisoning and certain other consequences of external causes',
    icdRange: 'S00-T98',
    diseases: [
      { name: 'Fracture of skull and facial bones', icd: 'S02' },
      { name: 'Fracture of neck, thorax or pelvis', icd: 'S12, S22, S32, T08' },
      { name: 'Fracture of femur', icd: 'S72' },
      { name: 'Fractures of other limb bones', icd: 'S42, S52, S62, S82, S92, T10, T12' },
      { name: 'Fractures involving multiple body regions', icd: 'T02' },
      { name: 'Dislocations, sprains and strains of specified and multiple body regions', icd: 'S03, S13, S23, S33, S43, S53, S63, S73, S83, S93, T03' },
      { name: 'Injury of eye and orbit', icd: 'S05' },
      { name: 'Intracranial injury', icd: 'S06' },
      { name: 'Injury of other internal organs', icd: 'S26–S27, S36–S37' },
      { name: 'Crushing injuries and traumatic amputations of specified and multiple body regions', icd: 'S07–S08, S17–S18, S28, S38, S47–S48, S57–S58, S67–S68, S77–S78, S87–S88, S97–S98, T04–T05' },
      { name: 'Other injuries of specified, unspecified and multiple body regions', icd: 'S00–S01, S04, S09–S11, S14–S16, S19–S21, S24–S25, S29–S31, S34–S35, S39–S41, S44–S46, S49–S51, S54–S56, S59–S61, S64–S66, S69–S71, S74–S76, S79–S81, S84–S86, S89–S91, S94–S96, S99, T00–T01, T06–T07, T09, T11, T13–T14' },
      { name: 'Open wound of unspecified body region with external cause; specifically animal bites, (e.g., bitten by rat, bitten or struck by dog, bitten or struck by other mammals)', icd: 'T14.1, W53; T14.1, W54; T14.1, W55' },
      { name: 'Effects of foreign body entering through natural orifice', icd: 'T15–T19' },
      { name: 'Burns and corrosions', icd: 'T20–T32' },
      { name: 'Poisoning by drugs and biological substances', icd: 'T36–T50' },
      { name: 'Toxic effects of substances chiefly nonmedicinal as to source', icd: 'T51–T65' },
      { name: 'Maltreatment syndromes', icd: 'T74' },
      { name: 'Other and unspecified effects of external causes', icd: 'T33–T35, T66–T73, T75–T78' },
      { name: 'Certain early complications of trauma and complications of surgical and medical care, not elsewhere classified', icd: 'T79–T88' },
      { name: 'Sequelae of injuries, poisoning and of other consequences of external causes', icd: 'T90–T98' },
    ],
  },
  {
    title: 'Codes for Special Purposes',
    icdRange: 'U00-U49',
    diseases: [
      { name: 'SARS', icd: 'U04' },
      { name: 'Vaping Disorder', icd: 'U07.0' },
      { name: 'COVID-19, virus identified', icd: 'U07.1' },
      { name: 'COVID-19, virus not identified', icd: 'U07.2' },
      { name: 'Multisystem inflammatory syndrome associated with COVID-19', icd: 'U10.9' },
      { name: 'Need for immunization against COVID-19', icd: 'U11.9' },
    ],
  },
];

/* ------------------------------------------------------------------ */
/*  Helpers                                                             */
/* ------------------------------------------------------------------ */

const ZERO_COUNT: SexCount = { male: 0, female: 0 };

function getCount(
  data: MorbidityReportData,
  rowKey: string,
  groupKey: AgeGroupKey
): SexCount {
  return data[rowKey]?.[groupKey] ?? ZERO_COUNT;
}

/** Sum of male/female/both across every age bracket for one disease row. */
function getGrandTotal(data: MorbidityReportData, rowKey: string) {
  return AGE_GROUPS.reduce(
    (acc, group) => {
      const { male, female } = getCount(data, rowKey, group.key);
      acc.male += male;
      acc.female += female;
      return acc;
    },
    { male: 0, female: 0 }
  );
}

/** View-only numeric cell — blank instead of "0" to keep this dense table readable. */
function ValueCell({ value, strong = false }: { value: number; strong?: boolean }) {
  return (
    <td
      className={`border border-gray-300 px-2 py-1 text-right text-sm tabular-nums ${
        strong ? 'font-semibold bg-gray-50' : ''
      }`}
    >
      {value > 0 ? value.toLocaleString() : ''}
    </td>
  );
}

/* ------------------------------------------------------------------ */
/*  Page                                                                */
/* ------------------------------------------------------------------ */

export default function MorbidityPage({
  data = {},
  facilityName,
  reportingPeriod,
}: MorbidityPageProps) {
  return (
    <>
      <Head title="Section A.1. Morbidity Report" />

      <div className="p-4 sm:p-6">
        <div className="mb-4">
          <h1 className="text-lg font-bold text-gray-900">
            Section A.1. Morbidity Report
          </h1>
          {(facilityName || reportingPeriod) && (
            <p className="text-sm text-gray-600">
              {facilityName}
              {facilityName && reportingPeriod ? ' — ' : ''}
              {reportingPeriod}
            </p>
          )}
          <p className="mt-1 text-xs text-gray-400">View only. Values sourced from submitted reports.</p>
        </div>

        <div className="overflow-x-auto border border-gray-300 rounded-md">
          <table className="min-w-full border-collapse">
            <thead>
              <tr>
                <th
                  rowSpan={2}
                  className="sticky left-0 z-20 bg-gray-100 border border-gray-300 px-2 py-1 text-left text-xs font-semibold text-gray-700 min-w-[260px]"
                >
                  Disease/s
                </th>
                <th
                  rowSpan={2}
                  className="sticky left-[260px] z-20 bg-gray-100 border border-gray-300 px-2 py-1 text-left text-xs font-semibold text-gray-700 min-w-[110px]"
                >
                  ICD-Code/s
                </th>
                {AGE_GROUPS.map((group) => (
                  <th
                    key={group.key}
                    colSpan={3}
                    className="bg-gray-100 border border-gray-300 px-2 py-1 text-center text-xs font-semibold text-gray-700 whitespace-nowrap"
                  >
                    {group.label}
                  </th>
                ))}
                <th
                  colSpan={3}
                  className="bg-gray-200 border border-gray-300 px-2 py-1 text-center text-xs font-semibold text-gray-700 whitespace-nowrap"
                >
                  Grand Total
                </th>
              </tr>
              <tr>
                {AGE_GROUPS.map((group) => (
                  <Fragment key={group.key}>
                    <th className="bg-gray-50 border border-gray-300 px-1 py-1 text-center text-[11px] font-medium text-gray-600">
                      M
                    </th>
                    <th className="bg-gray-50 border border-gray-300 px-1 py-1 text-center text-[11px] font-medium text-gray-600">
                      F
                    </th>
                    <th className="bg-gray-50 border border-gray-300 px-1 py-1 text-center text-[11px] font-medium text-gray-600">
                      T
                    </th>
                  </Fragment>
                ))}
                <th className="bg-gray-100 border border-gray-300 px-1 py-1 text-center text-[11px] font-medium text-gray-600">M</th>
                <th className="bg-gray-100 border border-gray-300 px-1 py-1 text-center text-[11px] font-medium text-gray-600">F</th>
                <th className="bg-gray-100 border border-gray-300 px-1 py-1 text-center text-[11px] font-medium text-gray-600">Both</th>
              </tr>
            </thead>
            <tbody>
              {MORBIDITY_SECTIONS.map((section) => (
                <Fragment key={section.title}>
                  <tr className="bg-gray-200">
                    <td
                      colSpan={2 + AGE_GROUPS.length * 3 + 3}
                      className="sticky left-0 border border-gray-300 px-2 py-1 text-xs font-bold text-gray-800"
                    >
                      {section.title}{' '}
                      <span className="font-normal text-gray-500">({section.icdRange})</span>
                    </td>
                  </tr>
                  {section.diseases.map((disease) => {
                    const rowKey = morbidityRowKey(disease.icd, disease.name);
                    const grandTotal = getGrandTotal(data, rowKey);
                    return (
                      <tr key={rowKey} className="odd:bg-white even:bg-gray-50 hover:bg-blue-50">
                        <td className="sticky left-0 z-10 bg-inherit border border-gray-300 px-2 py-1 text-sm text-gray-800 min-w-[260px]">
                          {disease.name}
                        </td>
                        <td className="sticky left-[260px] z-10 bg-inherit border border-gray-300 px-2 py-1 text-xs text-gray-500 min-w-[110px] whitespace-nowrap">
                          {disease.icd}
                        </td>
                        {AGE_GROUPS.map((group) => {
                          const count = getCount(data, rowKey, group.key);
                          return (
                            <Fragment key={`${rowKey}-${group.key}`}>
                              <ValueCell value={count.male} />
                              <ValueCell value={count.female} />
                              <ValueCell value={count.male + count.female} />
                            </Fragment>
                          );
                        })}
                        <ValueCell value={grandTotal.male} strong />
                        <ValueCell value={grandTotal.female} strong />
                        <ValueCell value={grandTotal.male + grandTotal.female} strong />
                      </tr>
                    );
                  })}
                </Fragment>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
}
