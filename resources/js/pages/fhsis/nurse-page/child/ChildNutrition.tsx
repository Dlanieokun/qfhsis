import React from 'react';
import TargetClientListTable, { TCLColumnGroup, TCLRow } from '../TargetClientListTable';

// Column structure derived from Child_Nutrition.xlsx ("TCL_Child_Nutri" sheet):
// TARGET CLIENT LIST FOR CHILD NUTRITION
export const childNutritionGroups: TCLColumnGroup[] = [
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
        title: 'Newborn (0-28 days old)',
        columns: [
            { key: 'lengthAtBirth', label: 'Length at Birth (cm)' },
            { key: 'weightAtBirth', label: 'Weight at Birth (kg)' },
            { key: 'birthWeightStatus', label: 'Status (L-Low <2,500g / N-Normal ≥2,500g / U-Unknown)' },
            { key: 'breastfeedingInit', label: 'Breastfeeding Initiated within 1 hr - Date' },
            { key: 'deliveryPlace', label: 'Place of Delivery' },
        ],
    },
    {
        title: 'Low Birth Weight: Iron Supplementation',
        columns: [
            { key: 'iron1mo', label: '1 month - Date' },
            { key: 'iron2mo', label: '2 months - Date' },
            { key: 'iron3mo', label: '3 months - Date' },
            { key: 'ironCompleted', label: 'Completed? (1-Yes/0-No, date completed)' },
        ],
    },
    {
        title: 'Vitamin A Supplementation (6-month interval)',
        columns: [
            { key: 'vitA611', label: '6-11 mos, 100,000 IU' },
            { key: 'vitA1259_1a', label: '12-59 mos, 200,000 IU - 1st dose (Yr 1)' },
            { key: 'vitA1259_1b', label: '12-59 mos, 200,000 IU - 2nd dose (Yr 1)' },
            { key: 'vitA1259_2a', label: '12-59 mos, 200,000 IU - 1st dose (Yr 2)' },
            { key: 'vitA1259_2b', label: '12-59 mos, 200,000 IU - 2nd dose (Yr 2)' },
        ],
    },
    {
        title: 'MNP (Micronutrient Powder)',
        columns: [
            { key: 'mnp611', label: '6-11 mos: 90 sachets/6 mos - Provided/Completed' },
            { key: 'mnp611Remarks', label: 'Remarks' },
            { key: 'mnp1223', label: '12-23 mos: 180 sachets/yr - Provided/Completed' },
            { key: 'mnp1223Remarks', label: 'Remarks' },
        ],
    },
    {
        title: 'LNS-SQ',
        columns: [
            { key: 'lns611', label: '6-11 mos: 1 sachet/day x120 days - Provided/Completed' },
            { key: 'lns611Remarks', label: 'Remarks' },
            { key: 'lns1223', label: '12-23 mos: 1 sachet/day x120 days - Provided/Completed' },
            { key: 'lns1223Remarks', label: 'Remarks' },
        ],
    },
    {
        title: 'MAM (Moderate Acute Malnutrition - SFP)',
        columns: [
            { key: 'mamIdentified', label: 'Identified (1-Yes/0-No)' },
            { key: 'mamEnrolled', label: 'Enrolled to SFP (1-Yes/0-No)' },
            { key: 'mamCured', label: 'Cured (1-Yes/0-No)' },
            { key: 'mamNonCured', label: 'Non-cured (1-Yes/0-No)' },
            { key: 'mamDefaulted', label: 'Defaulted (1-Yes/0-No)' },
            { key: 'mamDied', label: 'Died (1-Yes/0-No)' },
        ],
    },
    {
        title: 'SAM (Severe Acute Malnutrition - OTC)',
        columns: [
            { key: 'samIdentified', label: 'Identified (1-Yes/0-No)' },
            { key: 'samAdmitted', label: 'Admitted w/o Complication (1-Yes/0-No)' },
            { key: 'samCured', label: 'Cured (1-Yes/0-No)' },
            { key: 'samNonCured', label: 'Non-cured (1-Yes/0-No)' },
            { key: 'samDefaulted', label: 'Defaulted (1-Yes/0-No)' },
            { key: 'samDied', label: 'Died (1-Yes/2-No)' },
        ],
    },
    { title: '', columns: [{ key: 'remarks', label: 'Remarks / Actions Taken', width: '200px' }] },
];

export interface ChildNutritionProps {
    rows: TCLRow[];
    onChange: (rows: TCLRow[]) => void;
}

export default function ChildNutrition({ rows, onChange }: ChildNutritionProps) {
    return (
        <TargetClientListTable
            title="Target Client List for Child Nutrition"
            subtitle="Newborn status, Iron & Vitamin A supplementation, MNP, LNS-SQ, and MAM/SAM (SFP/OTC) monitoring"
            groups={childNutritionGroups}
            rows={rows}
            onChange={onChange}
        />
    );
}
