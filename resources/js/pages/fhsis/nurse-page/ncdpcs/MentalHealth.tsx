import React from 'react';
import TargetClientListTable, { TCLColumnGroup, TCLRow } from '../TargetClientListTable';

// Column structure derived from Mental_Health.xlsx ("TCL_MH" sheet):
// Target Client List for Mental Health
export const mentalHealthGroups: TCLColumnGroup[] = [
    {
        title: '',
        columns: [
            { key: 'dateAssessed', label: 'Date of Assessment', width: '130px' },
            { key: 'familySerial', label: 'Family Serial Number', width: '130px' },
            { key: 'name', label: 'Name (Last, First, MI)', width: '200px' },
            { key: 'address', label: 'Complete Address', width: '200px' },
            { key: 'dob', label: 'Date of Birth', width: '110px' },
            { key: 'ageYears', label: 'Age (years)', width: '90px' },
            { key: 'ageGroup', label: 'Age Group (A:0-9 / B:10-19 / C:20-59 / D:60+)', width: '150px' },
            { key: 'sex', label: 'Sex (M/F)', width: '80px' },
            { key: 'mhgapScreened', label: 'Screened using mhGAP (1-Yes/0-No)', width: '150px' },
        ],
    },
];

export interface MentalHealthProps {
    rows: TCLRow[];
    onChange: (rows: TCLRow[]) => void;
}

export default function MentalHealth({ rows, onChange }: MentalHealthProps) {
    return (
        <TargetClientListTable
            title="Target Client List for Mental Health"
            subtitle="Mental Health Gap Action Programme (mhGAP) screening records"
            groups={mentalHealthGroups}
            rows={rows}
            onChange={onChange}
        />
    );
}
