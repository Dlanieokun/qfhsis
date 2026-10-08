import { Head, useForm } from '@inertiajs/react';
import axios from 'axios';
import { Building2, LoaderCircle, Lock, Mail, MapPin, Phone, ShieldCheck, Stethoscope, User } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// If you moved the location routes out of api.php (see earlier fix),
// change this one line to '/qfhsis/public/locations'.
const LOCATION_API = '/qfhsis/public/api/locations';

interface RegisterForm {
    name: string;
    email: string;
    contact_number: number | '';
    password: string;
    password_confirmation: string;
    role: 'Doctor' | 'Public Health Nurse' | 'BHS' | 'BHW';
    assigned_facility: string;
    region: string;
    region_code: string;
    province: string;
    province_code: string;
    municipality: string;
    municipality_code: string;
    barangay: string[];
    barangay_codes: string[];
}

interface LocationItem {
    regCode?: string;
    regDesc?: string;
    provCode?: string;
    provDesc?: string;
    citymunCode?: string;
    citymunDesc?: string;
    brgyCode?: string;
    brgyDesc?: string;
}

// Shared field styling, identical to the inputs on login.tsx
const inputClass =
    'h-12 bg-slate-50 border-slate-200 text-slate-900 transition-all duration-300 focus-visible:bg-white ' +
    'focus-visible:ring-2 focus-visible:ring-blue-600/20 focus-visible:border-blue-600 hover:border-slate-300';

const iconWrapClass =
    'absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400 group-focus-within:text-blue-600 transition-colors';

const selectClass =
    'flex h-12 w-full rounded-md border border-slate-200 bg-slate-50 px-3 text-sm text-slate-900 shadow-sm transition-all duration-300 ' +
    'hover:border-slate-300 focus-visible:outline-none focus-visible:bg-white focus-visible:border-blue-600 ' +
    'focus-visible:ring-2 focus-visible:ring-blue-600/20 disabled:cursor-not-allowed disabled:opacity-50';

const labelClass = 'text-sm font-semibold text-slate-700';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm<RegisterForm>({
        name: '',
        email: '',
        contact_number: '',
        password: '',
        password_confirmation: '',
        role: 'BHW' as RegisterForm['role'],
        assigned_facility: '',
        region: '',
        region_code: '',
        province: '',
        province_code: '',
        municipality: '',
        municipality_code: '',
        barangay: [],
        barangay_codes: [],
    });

    const [regions, setRegions] = useState<LocationItem[]>([]);
    const [provinces, setProvinces] = useState<LocationItem[]>([]);
    const [municipalities, setMunicipalities] = useState<LocationItem[]>([]);
    const [barangays, setBarangays] = useState<LocationItem[]>([]);

    useEffect(() => {
        axios.get(`${LOCATION_API}/regions`).then((res) => setRegions(res.data));
    }, []);

    const handleRegionChange = async (e: React.ChangeEvent<HTMLSelectElement>) => {
        const code = e.target.value;
        const reg = regions.find((r) => r.regCode === code);

        setData((d) => ({
            ...d,
            region_code: code,
            region: reg?.regDesc || '',
            province_code: '',
            province: '',
            municipality_code: '',
            municipality: '',
            barangay_codes: [],
            barangay: [],
        }));

        setMunicipalities([]);
        setBarangays([]);
        if (code) {
            const res = await axios.get(`${LOCATION_API}/provinces/${code}`);
            setProvinces(res.data);
        } else {
            setProvinces([]);
        }
    };

    const handleProvinceChange = async (e: React.ChangeEvent<HTMLSelectElement>) => {
        const code = e.target.value;
        const prov = provinces.find((p) => p.provCode === code);

        setData((d) => ({
            ...d,
            province_code: code,
            province: prov?.provDesc || '',
            municipality_code: '',
            municipality: '',
            barangay_codes: [],
            barangay: [],
        }));

        setBarangays([]);
        if (code) {
            const res = await axios.get(`${LOCATION_API}/municipalities/${code}`);
            setMunicipalities(res.data);
        } else {
            setMunicipalities([]);
        }
    };

    const handleMunicipalityChange = async (e: React.ChangeEvent<HTMLSelectElement>) => {
        const code = e.target.value;
        const mun = municipalities.find((m) => m.citymunCode === code);

        setData((d) => ({
            ...d,
            municipality_code: code,
            municipality: mun?.citymunDesc || '',
            barangay_codes: [],
            barangay: [],
        }));

        if (code) {
            const res = await axios.get(`${LOCATION_API}/barangays/${code}`);
            setBarangays(res.data);
        } else {
            setBarangays([]);
        }
    };

    const handleBarangayCheckboxChange = (brgyCode: string, brgyDesc: string, isChecked: boolean) => {
        let updatedCodes = [...data.barangay_codes];
        let updatedDescs = [...data.barangay];

        if (isChecked) {
            if (!updatedCodes.includes(brgyCode)) {
                updatedCodes.push(brgyCode);
                updatedDescs.push(brgyDesc);
            }
        } else {
            updatedCodes = updatedCodes.filter((c) => c !== brgyCode);
            updatedDescs = updatedDescs.filter((d) => d !== brgyDesc);
        }

        setData((d) => ({ ...d, barangay_codes: updatedCodes, barangay: updatedDescs }));
    };

    const handleSelectAllBarangays = () => {
        setData((d) => ({
            ...d,
            barangay_codes: barangays.map((b) => b.brgyCode || ''),
            barangay: barangays.map((b) => b.brgyDesc || ''),
        }));
    };

    const handleClearAllBarangays = () => {
        setData((d) => ({ ...d, barangay_codes: [], barangay: [] }));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <div className="min-h-screen w-full flex bg-slate-50">
            <Head title="Register" />

            {/* Left Side - Deep Teal Branding (same panel as login) */}
            <div className="hidden lg:flex flex-col justify-between w-1/2 lg:w-5/12 p-12 bg-gradient-to-b from-teal-950 to-teal-800 text-white relative overflow-hidden lg:sticky lg:top-0 lg:h-screen">
                <div className="absolute top-[-10%] left-[-10%] w-96 h-96 bg-teal-600/20 rounded-full blur-3xl" />
                <div className="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-emerald-500/20 rounded-full blur-3xl" />

                <div className="relative z-10 animate-in fade-in slide-in-from-left-8 duration-1000">
                    <div className="flex items-center gap-3 mb-8">
                        <div className="p-2 bg-white/10 rounded-lg backdrop-blur-sm border border-white/20">
                            <Stethoscope className="w-6 h-6 text-emerald-300" />
                        </div>
                        <span className="text-2xl font-bold tracking-wider">FHSIS</span>
                    </div>
                    <h1 className="text-4xl font-bold leading-tight mt-12 mb-6">
                        Field Health Service <br /> Information System
                    </h1>
                    <p className="text-teal-100/80 text-lg max-w-md">
                        Request access for your health facility. An administrator will review and activate your account before you can sign in.
                    </p>
                </div>

                <div className="relative z-10 text-sm text-teal-200/60 animate-in fade-in duration-1000 delay-500">
                    &copy; {new Date().getFullYear()} FHSIS Portal. All rights reserved.
                </div>
            </div>

            {/* Right Side - Form Container */}
            <div className="flex-1 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-16 xl:px-24 bg-white">
                <div className="mx-auto w-full max-w-xl animate-in fade-in slide-in-from-bottom-8 duration-700">
                    {/* Mobile Header */}
                    <div className="flex lg:hidden items-center gap-2 mb-8">
                        <div className="p-2 bg-teal-900 rounded-lg">
                            <Stethoscope className="w-5 h-5 text-emerald-300" />
                        </div>
                        <span className="text-xl font-bold text-teal-950">FHSIS</span>
                    </div>

                    <div className="mb-8">
                        <h2 className="text-3xl font-bold text-slate-900 tracking-tight">Create an account</h2>
                        <p className="text-slate-500 mt-2">Enter your details below to request access</p>
                    </div>

                    <form className="space-y-6" onSubmit={submit}>
                        <div className="space-y-5">
                            {/* Name */}
                            <div className="grid gap-2 group">
                                <Label htmlFor="name" className={labelClass}>
                                    Full name
                                </Label>
                                <div className="relative">
                                    <div className={iconWrapClass}>
                                        <User className="h-5 w-5" />
                                    </div>
                                    <Input
                                        id="name"
                                        type="text"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        disabled={processing}
                                        placeholder="Juan Dela Cruz"
                                        className={`pl-11 ${inputClass}`}
                                    />
                                </div>
                                <InputError message={errors.name} />
                            </div>

                            {/* Email */}
                            <div className="grid gap-2 group">
                                <Label htmlFor="email" className={labelClass}>
                                    Email address
                                </Label>
                                <div className="relative">
                                    <div className={iconWrapClass}>
                                        <Mail className="h-5 w-5" />
                                    </div>
                                    <Input
                                        id="email"
                                        type="email"
                                        required
                                        tabIndex={2}
                                        autoComplete="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        disabled={processing}
                                        placeholder="name@example.com"
                                        className={`pl-11 ${inputClass}`}
                                    />
                                </div>
                                <InputError message={errors.email} />
                            </div>

                            {/* Contact Number */}
                            <div className="grid gap-2 group">
                                <Label htmlFor="contact_number" className={labelClass}>
                                    Contact number
                                </Label>
                                <div className="relative">
                                    <div className={iconWrapClass}>
                                        <Phone className="h-5 w-5" />
                                    </div>
                                    <Input
                                        id="contact_number"
                                        type="number"
                                        tabIndex={3}
                                        autoComplete="tel"
                                        value={data.contact_number}
                                        onChange={(e) => setData('contact_number', e.target.value === '' ? '' : parseInt(e.target.value, 10))}
                                        disabled={processing}
                                        placeholder="e.g. 09171234567"
                                        className={`pl-11 ${inputClass}`}
                                    />
                                </div>
                                <InputError message={errors.contact_number} />
                            </div>

                            {/* Passwords */}
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2 group col-span-2 sm:col-span-1">
                                    <Label htmlFor="password" className={labelClass}>
                                        Password
                                    </Label>
                                    <div className="relative">
                                        <div className={iconWrapClass}>
                                            <Lock className="h-5 w-5" />
                                        </div>
                                        <Input
                                            id="password"
                                            type="password"
                                            required
                                            tabIndex={4}
                                            autoComplete="new-password"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            disabled={processing}
                                            placeholder="••••••••"
                                            className={`pl-11 ${inputClass}`}
                                        />
                                    </div>
                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-2 group col-span-2 sm:col-span-1">
                                    <Label htmlFor="password_confirmation" className={labelClass}>
                                        Confirm password
                                    </Label>
                                    <div className="relative">
                                        <div className={iconWrapClass}>
                                            <Lock className="h-5 w-5" />
                                        </div>
                                        <Input
                                            id="password_confirmation"
                                            type="password"
                                            required
                                            tabIndex={5}
                                            autoComplete="new-password"
                                            value={data.password_confirmation}
                                            onChange={(e) => setData('password_confirmation', e.target.value)}
                                            disabled={processing}
                                            placeholder="••••••••"
                                            className={`pl-11 ${inputClass}`}
                                        />
                                    </div>
                                    <InputError message={errors.password_confirmation} />
                                </div>
                            </div>

                            {/* Role */}
                            <div className="grid gap-2 group">
                                <Label htmlFor="role" className={labelClass}>
                                    Role
                                </Label>
                                <div className="relative">
                                    <div className={iconWrapClass}>
                                        <ShieldCheck className="h-5 w-5" />
                                    </div>
                                    <select
                                        id="role"
                                        tabIndex={6}
                                        value={data.role}
                                        onChange={(e) => setData('role', e.target.value as RegisterForm['role'])}
                                        disabled={processing}
                                        className={`pl-11 ${selectClass}`}
                                    >
                                        <option value="BHW">BHW</option>
                                        <option value="BHS">Midwife</option>
                                        <option value="Public Health Nurse">Public Health Nurse</option>
                                        <option value="Doctor">Doctor</option>
                                    </select>
                                    <InputError message={errors.role} />
                                </div>
                            </div>

                            {/* Facility */}
                            <div className="grid gap-2 group">
                                <Label htmlFor="assigned_facility" className={labelClass}>
                                    Health facility
                                </Label>
                                <div className="relative">
                                    <div className={iconWrapClass}>
                                        <Building2 className="h-5 w-5" />
                                    </div>
                                    <Input
                                        id="assigned_facility"
                                        type="text"
                                        tabIndex={7}
                                        value={data.assigned_facility}
                                        onChange={(e) => setData('assigned_facility', e.target.value)}
                                        disabled={processing}
                                        placeholder="e.g. District Health Center Hub 1"
                                        className={`pl-11 ${inputClass}`}
                                    />
                                </div>
                                <InputError message={errors.assigned_facility} />
                            </div>
                        </div>

                        {/* Jurisdiction */}
                        <div className="rounded-xl border border-slate-200 bg-slate-50/60 p-5 space-y-5">
                            <div className="flex items-center gap-2 text-teal-900">
                                <MapPin className="h-5 w-5 text-blue-600" />
                                <h3 className="text-base font-semibold">Assigned jurisdiction</h3>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="col-span-2 grid gap-2 sm:col-span-1">
                                    <Label htmlFor="region_code" className={labelClass}>
                                        Region
                                    </Label>
                                    <select
                                        id="region_code"
                                        tabIndex={8}
                                        value={data.region_code}
                                        onChange={handleRegionChange}
                                        disabled={processing}
                                        className={selectClass}
                                    >
                                        <option value="">Select region…</option>
                                        {regions.map((r) => (
                                            <option key={r.regCode} value={r.regCode}>
                                                {r.regDesc}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.region_code} />
                                </div>

                                <div className="col-span-2 grid gap-2 sm:col-span-1">
                                    <Label htmlFor="province_code" className={labelClass}>
                                        Province
                                    </Label>
                                    <select
                                        id="province_code"
                                        tabIndex={9}
                                        value={data.province_code}
                                        onChange={handleProvinceChange}
                                        disabled={processing || !data.region_code}
                                        className={selectClass}
                                    >
                                        <option value="">Select province…</option>
                                        {provinces.map((p) => (
                                            <option key={p.provCode} value={p.provCode}>
                                                {p.provDesc}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.province_code} />
                                </div>

                                <div className="col-span-2 grid gap-2">
                                    <Label htmlFor="municipality_code" className={labelClass}>
                                        Municipality / City
                                    </Label>
                                    <select
                                        id="municipality_code"
                                        tabIndex={10}
                                        value={data.municipality_code}
                                        onChange={handleMunicipalityChange}
                                        disabled={processing || !data.province_code}
                                        className={selectClass}
                                    >
                                        <option value="">Select municipality…</option>
                                        {municipalities.map((m) => (
                                            <option key={m.citymunCode} value={m.citymunCode}>
                                                {m.citymunDesc}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.municipality_code} />
                                </div>

                                <div className="col-span-2 grid gap-2">
                                    <div className="flex items-end justify-between">
                                        <Label className={`${labelClass} flex items-center gap-2`}>
                                            <span>Barangays</span>
                                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-normal text-slate-500">
                                                {data.barangay_codes.length} selected
                                            </span>
                                        </Label>

                                        {barangays.length > 0 && (
                                            <div className="flex gap-3 text-xs font-medium">
                                                <button
                                                    type="button"
                                                    onClick={handleSelectAllBarangays}
                                                    disabled={processing}
                                                    className="text-blue-600 transition hover:text-blue-800"
                                                >
                                                    Select all
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={handleClearAllBarangays}
                                                    disabled={processing}
                                                    className="text-slate-500 transition hover:text-slate-800"
                                                >
                                                    Clear
                                                </button>
                                            </div>
                                        )}
                                    </div>

                                    <div
                                        className={`max-h-44 space-y-1 overflow-y-auto rounded-md border border-slate-200 bg-white p-2 transition ${
                                            !data.municipality_code ? 'pointer-events-none opacity-50' : ''
                                        }`}
                                    >
                                        {barangays.length === 0 ? (
                                            <p className="p-2 text-sm italic text-slate-400">Select a municipality first…</p>
                                        ) : (
                                            barangays.map((b) => (
                                                <label
                                                    key={b.brgyCode}
                                                    className="group flex cursor-pointer select-none items-center gap-3 rounded-lg p-2 transition hover:bg-blue-50/60"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        value={b.brgyCode}
                                                        checked={data.barangay_codes.includes(b.brgyCode || '')}
                                                        onChange={(e) =>
                                                            handleBarangayCheckboxChange(b.brgyCode || '', b.brgyDesc || '', e.target.checked)
                                                        }
                                                        disabled={processing}
                                                        className="h-4 w-4 cursor-pointer rounded border-slate-300 text-blue-600 focus:ring-2 focus:ring-blue-500"
                                                    />
                                                    <span className="text-sm text-slate-700 transition group-hover:text-slate-900">
                                                        {b.brgyDesc}
                                                    </span>
                                                </label>
                                            ))
                                        )}
                                    </div>
                                    <InputError message={errors.barangay_codes} />
                                </div>
                            </div>
                        </div>

                        <Button
                            type="submit"
                            className="w-full h-12 text-base font-semibold shadow-md bg-blue-600 hover:bg-blue-700 text-white transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98]"
                            tabIndex={11}
                            disabled={processing}
                        >
                            {processing ? <LoaderCircle className="mr-2 h-5 w-5 animate-spin" /> : null}
                            Create account
                        </Button>

                        <div className="text-center text-sm text-slate-500">
                            Already have an account?{' '}
                            <TextLink href={route('login')} tabIndex={12} className="font-medium text-blue-600 hover:text-blue-700">
                                Log in
                            </TextLink>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}