import React from 'react';
import TargetClientListTable, { TCLColumnGroup, TCLRow } from '../TargetClientListTable';

// Column structure derived from Child_Immunication.xlsx ("TCL_Child_immu" sheet):
// TARGET CLIENT LIST FOR CHILD IMMUNIZATION
export const childImmunizationGroups: TCLColumnGroup[] = [
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
            { key: 'cpab', label: 'Children Protected at Birth (CPAB)', width: '110px' },
        ],
    },
    { title: 'BCG', columns: [{ key: 'bcgDate', label: 'Date Given' }] },
    { title: 'Hepa B', columns: [{ key: 'hepaBDate', label: 'Date Given (within 24 hrs)' }] },
    {
        title: 'DPT-HiB-HepB',
        columns: [
            { key: 'dpt1', label: '1st dose (1½ mos)' },
            { key: 'dpt2', label: '2nd dose (2½ mos)' },
            { key: 'dpt3', label: '3rd dose (3½ mos)' },
        ],
    },
    {
        title: 'OPV',
        columns: [
            { key: 'opv1', label: '1st dose (1½ mos)' },
            { key: 'opv2', label: '2nd dose (2½ mos)' },
            { key: 'opv3', label: '3rd dose (3½ mos)' },
        ],
    },
    {
        title: 'IPV',
        columns: [
            { key: 'ipv1', label: '1st dose (3½ mos)' },
            { key: 'ipv2', label: '2nd dose (9 mos)' },
        ],
    },
    {
        title: 'PCV',
        columns: [
            { key: 'pcv1', label: '1st dose (1½ mos)' },
            { key: 'pcv2', label: '2nd dose (2½ mos)' },
            { key: 'pcv3', label: '3rd dose (3½ mos)' },
        ],
    },
    {
        title: 'MMR',
        columns: [
            { key: 'mmr1', label: '1st dose (9 mos)' },
            { key: 'mmr2', label: '2nd dose (12 mos)' },
        ],
    },
    { title: 'FIC', columns: [{ key: 'ficDate', label: 'Date Completed (0-11 mos)' }] },
    { title: 'CIC', columns: [{ key: 'cicDate', label: 'Date Completed (0-11 mos)' }] },
    { title: '', columns: [{ key: 'remarks', label: 'Remarks / Actions Taken', width: '200px' }] },
];

export interface ChildImmunizationProps {
    rows: TCLRow[];
    onChange: (rows: TCLRow[]) => void;
}

export default function ChildImmunization({ rows, onChange }: ChildImmunizationProps) {
    return (
        <TargetClientListTable
            title="Target Client List for Child Immunization"
            subtitle="BCG, Hepa B, DPT-HiB-HepB, OPV, IPV, PCV, MMR, FIC & CIC tracking"
            legend={
                <span>
                    <strong>FIC</strong> (Fully Immunized Child, 0-11 mos): 1 dose BCG + 3 doses DPT-HiB-HepB + 3
                    doses OPV + 2 doses MMR. <strong>CIC</strong> (Completely Immunized Child): FIC of the previous
                    year.
                </span>
            }
            groups={childImmunizationGroups}
            rows={rows}
            onChange={onChange}
        />
    );
}
