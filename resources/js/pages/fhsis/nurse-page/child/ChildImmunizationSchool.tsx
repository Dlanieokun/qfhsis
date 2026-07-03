import React from 'react';
import TargetClientListTable, { TCLColumnGroup, TCLRow } from '../TargetClientListTable';

// Column structure derived from Child_Immunication_School.xlsx ("TCL_Child_School_immu" sheet):
// TARGET CLIENT LIST FOR SCHOOL & COMMUNITY BASED IMMUNIZATION
export const childImmunizationSchoolGroups: TCLColumnGroup[] = [
    {
        title: '',
        columns: [
            { key: 'dateReg', label: 'Date of Registration', width: '130px' },
            { key: 'familySerial', label: 'Family Serial Number', width: '130px' },
            { key: 'childName', label: 'Name of Child (Last, First, MI)', width: '200px' },
            { key: 'dob', label: 'Date of Birth', width: '110px' },
            { key: 'sex', label: 'Sex (M/F)', width: '80px' },
            { key: 'ageYears', label: 'Age (years)', width: '90px' },
            { key: 'address', label: 'Complete Address', width: '200px' },
            { key: 'gradeLevel', label: 'Grade Level (A-Gr1, B-Gr4, C-Gr7, D-Not Enrolled)', width: '150px' },
            { key: 'tdVaccine', label: 'Tetanus Diphtheria Toxoid (Td) Date', width: '150px' },
            { key: 'mrVaccine', label: 'Measles Rubella (MR) Date', width: '150px' },
            { key: 'hpvDose1', label: '(SBI) HPV Vaccine 1st Dose Date', width: '150px' },
        ],
    },
    {
        title: 'Community Based Immunization (CBI)',
        columns: [
            { key: 'cbiHpvAge9', label: 'Female aged 9 yrs, 1st dose HPV date' },
            { key: 'cbiHpvDose2', label: 'HPV vaccine 2nd dose date' },
            { key: 'cbiCompleted', label: 'Completed 2 HPV doses? (1-Yes/0-No, date)' },
        ],
    },
    { title: '', columns: [{ key: 'remarks', label: 'Remarks / Actions Taken', width: '200px' }] },
];

export interface ChildImmunizationSchoolProps {
    rows: TCLRow[];
    onChange: (rows: TCLRow[]) => void;
}

export default function ChildImmunizationSchool({ rows, onChange }: ChildImmunizationSchoolProps) {
    return (
        <TargetClientListTable
            title="Target Client List for School & Community Based Immunization"
            subtitle="Td / MR / HPV vaccination tracking for Grade 1, 4 & 7 and community-based programs"
            groups={childImmunizationSchoolGroups}
            rows={rows}
            onChange={onChange}
        />
    );
}
