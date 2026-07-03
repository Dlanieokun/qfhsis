import React from 'react';
import TargetClientListTable, { TCLColumnGroup, TCLRow } from '../TargetClientListTable';

// Column structure derived from PhilPEN_Risk_Assessment.xlsx ("TCL_PhilPEN" sheet):
// Target Client List for PhilPEN Risk Assessment
export const philpenGroups: TCLColumnGroup[] = [
    {
        title: '',
        columns: [
            { key: 'dateAssessed', label: 'Date of Assessment', width: '130px' },
            { key: 'familySerial', label: 'Family Serial Number', width: '130px' },
            { key: 'name', label: 'Name (Last, First, MI)', width: '200px' },
            { key: 'address', label: 'Complete Address', width: '200px' },
            { key: 'dob', label: 'Date of Birth', width: '110px' },
            { key: 'ageYears', label: 'Age (years)', width: '90px' },
            { key: 'ageGroup', label: 'Age Group (A-20-59 / B-60+)', width: '110px' },
            { key: 'sex', label: 'Sex (M/F)', width: '80px' },
        ],
    },
    {
        title: 'Risk Factor Assessment',
        columns: [
            { key: 'currentSmoker', label: 'Current Smoker (3-Both/2-Vaporized/1-Tobacco/0-No)', width: '150px' },
            { key: 'btiAsk', label: 'ASK', width: '70px' },
            { key: 'btiAdvise', label: 'ADVISE', width: '80px' },
            { key: 'btiAssess', label: 'ASSESS', width: '80px' },
            { key: 'btiAssist', label: 'ASSIST', width: '80px' },
            { key: 'btiArrange', label: 'ARRANGE', width: '90px' },
            { key: 'btiProvided', label: 'Provided Brief Tobacco Intervention (1-Yes/0-No)', width: '150px' },
            { key: 'bingeAlcohol', label: 'Binge Alcohol Drinker (1-Yes/0-No)', width: '130px' },
            { key: 'insufficientActivity', label: 'Insufficient Physical Activity (1-Yes/0-No)', width: '140px' },
            { key: 'unhealthyDiet', label: 'Consumed Unhealthy Diet (1-Yes/0-No)', width: '140px' },
            { key: 'weightStatus', label: 'Overweight/Obese (0-Normal/1-Overweight/2-Obese)', width: '150px' },
        ],
    },
    {
        title: 'Hypertension Screening',
        columns: [
            { key: 'htnCompletedDate1', label: 'Completed Screening - 1st Reading', width: '150px' },
            { key: 'htnCompletedDate2', label: 'Completed Screening - 2nd Reading', width: '150px' },
            { key: 'htnResult', label: 'Result (1: ≥140/90 / 0: <140/90)', width: '130px' },
        ],
    },
    {
        title: 'Medicine Provision (Multidrug Therapy)',
        columns: [
            { key: 'medsInitialNeeded', label: 'No. of Medications Needed/Month (Initial)', width: '150px' },
            { key: 'medsChangeNeeded', label: 'No. of Medications Needed/Month (Change in Rx)', width: '160px' },
            { key: 'antihtnTablets', label: 'Antihypertensive Medication (# of Tablets)', width: '150px' },
        ],
    },
    {
        title: 'Monthly Antihypertensive Provision (PBF / OOP / Both)',
        columns: [
            { key: 'janProvision', label: 'January (PBF #: / OOP #: / Both if PBF≥60%)', width: '160px' },
            { key: 'febProvision', label: 'February (PBF #: / OOP #: / Both if PBF≥60%)', width: '160px' },
            { key: 'marProvision', label: 'March (PBF #: / OOP #: / Both if PBF≥60%)', width: '160px' },
            { key: 'aprProvision', label: 'April (PBF #: / OOP #: / Both if PBF≥60%)', width: '160px' },
            { key: 'mayProvision', label: 'May (PBF #: / OOP #: / Both if PBF≥60%)', width: '160px' },
            { key: 'junProvision', label: 'June (PBF #: / OOP #: / Both if PBF≥60%)', width: '160px' },
            { key: 'julProvision', label: 'July (PBF #: / OOP #: / Both if PBF≥60%)', width: '160px' },
            { key: 'augProvision', label: 'August (PBF #: / OOP #: / Both if PBF≥60%)', width: '160px' },
            { key: 'sepProvision', label: 'September (PBF #: / OOP #: / Both if PBF≥60%)', width: '170px' },
        ],
    },
];

export interface PhilPENProps {
    rows: TCLRow[];
    onChange: (rows: TCLRow[]) => void;
}

export default function PhilPEN({ rows, onChange }: PhilPENProps) {
    return (
        <TargetClientListTable
            title="Target Client List for PhilPEN Risk Assessment"
            subtitle="Risk factor assessment, Brief Tobacco Intervention (BTI), hypertension screening & antihypertensive medicine provision"
            legend={
                <span>
                    <strong>BTI 5A's:</strong> Ask, Advise, Assess, Assist, Arrange — provided if the client is a
                    current smoker (1, 2, or 3) and all 5 A's are marked 1. <strong>Binge drinking:</strong> ≥5
                    standard drinks in a day for males, ≥4 for females. <strong>PBF</strong> = Provided by Facility,{' '}
                    <strong>OOP</strong> = Out of Pocket. Monthly antihypertensive columns are checked "Both" only
                    when PBF reaches the 60% medication threshold for that month.
                </span>
            }
            groups={philpenGroups}
            rows={rows}
            onChange={onChange}
        />
    );
}
