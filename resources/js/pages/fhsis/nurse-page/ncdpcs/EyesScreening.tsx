import React from 'react';
import TargetClientListTable, { TCLColumnGroup, TCLRow } from '../TargetClientListTable';

// Column structure derived from Eyes_Screening.xlsx ("TCL_VA" sheet):
// Target Client List for Eye Ailment/s Screening
export const eyesScreeningGroups: TCLColumnGroup[] = [
    {
        title: '',
        columns: [
            { key: 'dateScreened', label: 'Date of Screening', width: '130px' },
            { key: 'familySerial', label: 'Family Serial Number', width: '130px' },
            { key: 'name', label: 'Name (Last, First, MI)', width: '200px' },
            { key: 'address', label: 'Complete Address', width: '200px' },
            { key: 'dob', label: 'Date of Birth', width: '110px' },
            { key: 'ageYears', label: 'Age (years)', width: '90px' },
            { key: 'ageGroup', label: 'Age Group (A:0-9 / B:10-19 / C:20-59 / D:60+)', width: '150px' },
            { key: 'sex', label: 'Sex (M/F)', width: '80px' },
            { key: 'screened', label: 'Screened for Eye Ailment/s (1-Yes/0-No)', width: '140px' },
        ],
    },
    {
        title: 'All Age Groups',
        columns: [
            {
                key: 'identifiedDisease',
                label: 'Identified with Eye Diseases (A-Vision, B-Appearance, C-Injury, D-Routine Exam, 0-No)',
                width: '180px',
            },
            { key: 'dateReferred', label: 'Date Referred to Eye Care Professional', width: '160px' },
        ],
    },
    { title: '', columns: [{ key: 'remarks', label: 'Remarks', width: '200px' }] },
];

export interface EyesScreeningProps {
    rows: TCLRow[];
    onChange: (rows: TCLRow[]) => void;
}

export default function EyesScreening({ rows, onChange }: EyesScreeningProps) {
    return (
        <TargetClientListTable
            title="Target Client List for Eye Ailment/s Screening"
            subtitle="Vision and eye disease screening and referral to eye care professionals"
            legend={
                <span>
                    <strong>Changes in Vision:</strong> Error of Refraction, Cataract, Glaucoma, Age-related Macular
                    Degeneration. <strong>Changes in Appearance:</strong> Strabismus, Pterygium, Eye Mass/Tumor,
                    Conjunctivitis, Blepharitis, Subconjunctival Hemorrhage, Hordeolum/Stye, Retinoblastoma.{' '}
                    <strong>Eye and Orbital Injury:</strong> Trauma, Chemical Burns, Foreign Body, Retinal
                    Detachment. <strong>Routine Eye Exams:</strong> Retinopathy of Prematurity (ROP) Screening,
                    Diabetic Retinopathy Screening.
                </span>
            }
            groups={eyesScreeningGroups}
            rows={rows}
            onChange={onChange}
        />
    );
}
