import React from 'react';
import TargetClientListTable, { TCLColumnGroup, TCLRow } from '../TargetClientListTable';

// Column structure derived from Child_Management_of_Sick.xlsx ("TCL_Child_MCI" sheet):
// TARGET CLIENT LIST FOR MANAGEMENT OF SICK
export const childManagementSickGroups: TCLColumnGroup[] = [
    {
        title: '',
        columns: [
            { key: 'dateReg', label: 'Date of Registration', width: '130px' },
            { key: 'familySerial', label: 'Family Serial Number', width: '130px' },
            { key: 'childName', label: 'Name of Child (Last, First, MI)', width: '200px' },
            { key: 'dob', label: 'Date of Birth', width: '110px' },
            { key: 'ageMonths', label: 'Age (months)', width: '90px' },
            { key: 'sex', label: 'Sex (M/F)', width: '80px' },
            { key: 'motherName', label: "Mother's Complete Name", width: '200px' },
            { key: 'address', label: 'Complete Address', width: '200px' },
        ],
    },
    {
        title: 'Vitamin A Supplementation (Sick Infants/Children)',
        columns: [
            { key: 'vitA611', label: '6-11 mos, 100,000 IU - Date Given' },
            { key: 'vitA1259', label: '12-59 mos, 200,000 IU - Date Given' },
            { key: 'diagnosis', label: 'Diagnosis/Findings (1-Measles, 2-Persistent Diarrhea)' },
        ],
    },
    {
        title: 'Acute Diarrhea Cases: Treatment Given',
        columns: [
            { key: 'orsOnly', label: 'ORS only - Date Given' },
            { key: 'orsZinc', label: 'ORS and Zinc drops/syrup - Date Given' },
        ],
    },
    {
        title: 'Pneumonia Cases: Treatment Given',
        columns: [
            { key: 'amoxicillin', label: 'Amoxicillin drops/suspension - Date' },
            { key: 'amoxClav', label: 'Amoxicillin-clavulanate suspension - Date' },
            { key: 'cefuroxime', label: 'Cefuroxime suspension - Date' },
            { key: 'othersTreatment', label: 'Others, please specify - Date' },
        ],
    },
    { title: '', columns: [{ key: 'remarks', label: 'Remarks / Actions Taken', width: '200px' }] },
];

export interface ChildManagementSickProps {
    rows: TCLRow[];
    onChange: (rows: TCLRow[]) => void;
}

export default function ChildManagementSick({ rows, onChange }: ChildManagementSickProps) {
    return (
        <TargetClientListTable
            title="Target Client List for Management of Sick"
            subtitle="Integrated Management of Childhood Illness (IMCI): Vitamin A, diarrhea & pneumonia case management"
            legend={
                <span>
                    <strong>Sick Infants/Children</strong> refers to those diagnosed with measles and/or diarrhea.
                    Vitamin A dosage: 100,000 IU for infants 6-11 months, 200,000 IU for children 12-59 months.
                    For measles cases, give one capsule upon diagnosis (regardless of last VAC dose) plus another
                    after 24 hours. For persistent diarrhea, give one capsule upon diagnosis unless VAC was given
                    less than 4 weeks prior.
                </span>
            }
            groups={childManagementSickGroups}
            rows={rows}
            onChange={onChange}
        />
    );
}
