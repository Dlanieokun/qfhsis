import React from 'react';
import TargetClientListTable, { TCLColumnGroup, TCLRow } from '../TargetClientListTable';

// Column structure derived from Cervical_Cancer_Screening.xlsx ("TCL_CANCER" sheet):
// Target Client List for Cervical Cancer Screening and Breast Mass Examination
export const cervicalCancerScreeningGroups: TCLColumnGroup[] = [
    {
        title: '',
        columns: [
            { key: 'dateAssessed', label: 'Date of Assessment', width: '130px' },
            { key: 'familySerial', label: 'Family Serial Number', width: '130px' },
            { key: 'name', label: 'Name (Last, First, MI)', width: '200px' },
            { key: 'address', label: 'Complete Address', width: '200px' },
            { key: 'dob', label: 'Date of Birth', width: '110px' },
            { key: 'ageYears', label: 'Age (years)', width: '90px' },
        ],
    },
    {
        title: 'Cervical Cancer',
        columns: [
            { key: 'cxScreeningDone', label: 'Screening Done (3-HPV DNA/2-Pap Smear/1-VIA/0-Assessed only)', width: '170px' },
            { key: 'cxResult', label: 'Result (2-Positive/1-Suspicious CA/0-Negative)', width: '150px' },
            { key: 'cxLinkedToCare', label: 'Linked to Care (2-Referred/1-Treated/0-No)', width: '150px' },
        ],
    },
    {
        title: 'Breast Cancer',
        columns: [
            { key: 'brRiskResult', label: 'Risk Assessment (2-High-risk/1-Symptomatic/0-Asymptomatic)', width: '170px' },
            { key: 'brAgeRiskClass', label: 'Age to Risk Class (A: 30-69 High-risk/Symptomatic, B: 50-69 Asymptomatic)', width: '180px' },
            { key: 'brExamination', label: 'Examination (CBE / M-Mammogram/Ultrasound)', width: '150px' },
            { key: 'brResults', label: 'Results (3-CBE Remarkable/2-Unremarkable, 1-BI-RADS 3-6/0-BI-RADS 0-2)', width: '190px' },
            { key: 'brLinkedToCare', label: 'Linked to Care (1-Referred/0-No)', width: '140px' },
        ],
    },
    { title: '', columns: [{ key: 'remarks', label: 'Remarks', width: '200px' }] },
];

export interface CervicalCancerScreeningProps {
    rows: TCLRow[];
    onChange: (rows: TCLRow[]) => void;
}

export default function CervicalCancerScreening({ rows, onChange }: CervicalCancerScreeningProps) {
    return (
        <TargetClientListTable
            title="Target Client List for Cervical Cancer Screening and Breast Mass Examination"
            subtitle="Cervical cancer (VIA/Pap Smear/HPV DNA) and breast cancer (CBE/Mammogram) screening & referral"
            legend={
                <span>
                    <strong>Breast Cancer High-risk Asymptomatic:</strong> 1st-degree relative diagnosed with breast
                    cancer at age 49 or below, or other risk factors identified by the health care provider.{' '}
                    <strong>Symptomatic:</strong> women with breast symptoms or complaints (lumps, pain, nipple
                    discharge, enlarged lymph nodes, orange-peel/skin dimpling, etc.).
                </span>
            }
            groups={cervicalCancerScreeningGroups}
            rows={rows}
            onChange={onChange}
        />
    );
}
