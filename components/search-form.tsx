'use client'

import React, { useState, useRef, useEffect } from 'react'
import { useForm, Controller, UseFormRegister, Control } from 'react-hook-form'
import { Switch, Disclosure } from '@headlessui/react'
import { ChevronUpIcon, QuestionMarkCircleIcon, XMarkIcon, PlusIcon, CheckIcon } from '@heroicons/react/24/solid'
import { AnimatePresence, motion } from 'framer-motion'
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Card, CardContent } from "@/components/ui/card"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet"

type FormData = {
  userLocation: string;
  maxDistance: number;
  mutations: string;
  biomarkers: string;
  previousSurgery?: 'Yes' | 'No';
  brainMetastases?: 'Yes' | 'No';
  cancerStage?: string;
  metastaticCancer?: 'Yes' | 'No';
  progressedPDL1?: 'Yes' | 'No';
  specificResistances: string;
  priorDrugProgression: string;
  priorDrugs: string;
  treatmentNaive?: 'Yes' | 'No';
  mutationMatchWeight?: number;
  mutationMismatchWeight?: number;
  mutationNotAllowedWeight?: number;
  mutationMentionedWeight?: number;
  mutationMentionedTitleWeight?: number;
  surgeryMatchWeight?: number;
  surgeryMismatchWeight?: number;
  biomarkerRequiredWeight?: number;
  biomarkerRequiredMismatchWeight?: number;
  biomarkerMentionedWeight?: number;
  biomarkerNotAllowedWeight?: number;
  biomarkerProgressionRequiredWeight?: number;
  brainMetastasesMatchWeight?: number;
  brainMetastasesMismatchWeight?: number;
  titleBrainMetastasesMatchWeight?: number;
  titleBrainMetastasesMismatchWeight?: number;
  drugRequiredWeight?: number;
  drugNotAllowedWeight?: number;
  notAllowedBrainMetastasesMatchWeight?: number;
  notAllowedBrainMetastasesMismatchWeight?: number;
  cancerStageMatchWeight?: number;
  metastaticCancerMatchWeight?: number;
  metastaticCancerMismatchWeight?: number;
  pdl1ProgressionMatchWeight?: number;
  pdl1ProgressionMismatchWeight?: number;
  resistanceRequiredMatchWeight?: number;
  resistanceRequiredMismatchWeight?: number;
  resistanceSoughtMatchWeight?: number;
  drugProgressionRequiredMatchWeight?: number;
  drugProgressionRequiredMismatchWeight?: number;
  drugProgressionSoughtMatchWeight?: number;
  treatmentNaiveRequiredMatchWeight?: number;
  treatmentNaiveRequiredMismatchWeight?: number;
}

type Trial = {
  NCTId: string
  title: string
  summary: string
  score: number
  enrollment: number
  distance?: number
  WithinRangeZips: string[]
  enrollmentChange: number
  scoringDetails: Array<{
    criterion: string
    score: number
  }>
  priorTrialResults: PriorTrialResult[]
  LocationZip?: string
}

type PriorTrialResult = {
  id: string;
  drugNames: string[];
  pfs: number;
  orr: number;
  os: number;
  genesMutations: string[];
  priorTreatments: string[];
  matchScore: number;
  otherCharacteristics: string;
}

// Update the type definitions to match the PHP response
type PHPTrialResponse = {
  NCTId: string;
  BriefTitle?: string;
  BriefSummary?: string;
  Score?: number;
  Enrollment?: string | number;
  Distance?: number;
  WithinRangeZips?: string[];
  enrollmentChange?: number;
  ScoringDetails?: Array<{
    criterion: string;
    score: number;
  }>;
  priorTrialResults?: PHPPriorTrialResult[];
  LocationZip?: string;
}

type PHPResponse = {
  trials: PHPTrialResponse[];
  debug: string[];
}

// Add these interfaces at the top of the file
interface PHPPriorTrialResult {
  id?: string;
  drugNames?: string[];
  pfs?: string | number;
  orr?: string | number;
  os?: string | number;
  genesMutations?: string[];
  priorTreatments?: string[];
  matchScore?: string | number;
  otherCharacteristics?: string;
}

const WeightInput: React.FC<{
  label: string;
  name: keyof FormData;
  control: Control<FormData>;
}> = ({ label, name, control }) => (
  <div className="mt-2">
    <label className="block text-sm font-medium text-gray-700">
      {label}
    </label>
    <Controller
      name={name}
      control={control}
      defaultValue={0}
      render={({ field }) => (
        <input
          type="number"
          {...field}
          className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
        />
      )}
    />
  </div>
)

const InfoPopup: React.FC<{ description: string }> = ({ description }) => (
  <Popover>
    <PopoverTrigger asChild>
      <QuestionMarkCircleIcon className="h-5 w-5 text-blue-500 ml-2 cursor-pointer" />
    </PopoverTrigger>
    <PopoverContent className="w-80 p-2 text-sm">
      {description}
    </PopoverContent>
  </Popover>
)

const ExpandableSection: React.FC<{ 
  inputProps: React.InputHTMLAttributes<HTMLInputElement>;
  label: string;
  suggestions: string[];
  description: string;
  register: UseFormRegister<FormData>;
  name: keyof FormData;
  children: React.ReactNode;
}> = ({ inputProps, label, suggestions, description, register, name, children }) => {
  const [isOpen, setIsOpen] = useState(false)
  const [showSuggestions, setShowSuggestions] = useState(false)
  const suggestionsRef = useRef<HTMLDivElement>(null)
  const [inputValue, setInputValue] = useState('')

  const { onChange, onBlur, name: fieldName, ref } = register(name)

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setInputValue(e.target.value)
    onChange(e)
  }

  const handleSuggestionClick = (suggestion: string) => {
    const values = inputValue.split(',').map(v => v.trim()).filter(Boolean)
    if (!values.includes(suggestion)) {
      const newValue = [...values, suggestion].join(', ')
      setInputValue(newValue)
      onChange({ target: { name: fieldName, value: newValue } } as React.ChangeEvent<HTMLInputElement>)
    }
    setShowSuggestions(false)
  }

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (suggestionsRef.current && !suggestionsRef.current.contains(event.target as Node)) {
        setShowSuggestions(false)
      }
    }

    document.addEventListener('mousedown', handleClickOutside)
    return () => {
      document.removeEventListener('mousedown', handleClickOutside)
    }
  }, [])

  return (
    <Disclosure>
      {({ open }) => (
        <>
          <div className="mb-4 relative">
            <Label htmlFor={inputProps.id} className="mb-1 flex items-center">
              {label}
              <InfoPopup description={description} />
            </Label>
            <div className="flex items-center w-full rounded-lg border border-blue-200 bg-gradient-to-r from-blue-50 to-blue-100 shadow-sm">
              <Input
                {...inputProps}
                ref={ref}
                value={inputValue}
                onChange={handleInputChange}
                onBlur={onBlur}
                name={fieldName}
                className="w-full rounded-l-lg bg-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                onClick={() => setShowSuggestions(true)}
              />
              <Disclosure.Button 
                className="p-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                onClick={() => setIsOpen(!isOpen)}
              >
                <ChevronUpIcon
                  className={`${open ? 'rotate-180 transform' : ''} h-5 w-5 text-blue-600`}
                />
              </Disclosure.Button>
            </div>
            <AnimatePresence>
              {showSuggestions && (
                <motion.div
                  ref={suggestionsRef}
                  initial={{ opacity: 0, y: -10 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: -10 }}
                  transition={{ duration: 0.2 }}
                  className="absolute z-10 w-full mt-1 bg-white border border-blue-200 rounded-md shadow-lg"
                >
                  <ScrollArea className="h-60 w-full p-2">
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
                      {suggestions.map((suggestion) => (
                        <div
                          key={suggestion}
                          className="px-2 py-1 hover:bg-blue-100 cursor-pointer rounded text-xs"
                          onMouseDown={() => handleSuggestionClick(suggestion)}
                        >
                          {suggestion}
                        </div>
                      ))}
                    </div>
                  </ScrollArea>
                </motion.div>
              )}
            </AnimatePresence>
          </div>
          <Disclosure.Panel className="px-4 pt-4 pb-2 text-sm text-gray-500 bg-white rounded-lg mb-4 border border-gray-200">
            {children}
          </Disclosure.Panel>
        </>
      )}
    </Disclosure>
  )
}

const ComparisonPanel: React.FC<{ 
  selectedResults: PriorTrialResult[],
  onClose: () => void
}> = ({ selectedResults, onClose }) => {
  return (
    <div className="p-4">
      <div className="flex justify-between items-center mb-4">
        <h3 className="text-xl font-bold">Compare Clinical Trial Results</h3>
        <button onClick={onClose} className="text-gray-500 hover:text-gray-700">
          <XMarkIcon className="h-6 w-6" />
        </button>
      </div>
      <div className="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Trial</TableHead>
              <TableHead>Drug Name(s)</TableHead>
              <TableHead>PFS</TableHead>
              <TableHead>ORR</TableHead>
              <TableHead>OS</TableHead>
              <TableHead>Genes/Mutations</TableHead>
              <TableHead>Prior Treatments</TableHead>
              <TableHead>Match Score</TableHead>
              <TableHead>Other Characteristics</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {selectedResults.map((result) => (
                <TableRow key={result.id}>
                  <TableCell>{result.drugNames.join(', ')}</TableCell>
                  <TableCell>{result.pfs.toFixed(1)} months</TableCell>
                  <TableCell>{(result.orr * 100).toFixed(1)}%</TableCell>
                  <TableCell>{result.os.toFixed(1)} months</TableCell>
                  <TableCell>{result.genesMutations.join(', ')}</TableCell>
                  <TableCell>{result.priorTreatments.join(', ')}</TableCell>
                  <TableCell>
                    <div className="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                      <div className="bg-blue-600 h-2.5 rounded-full" style={{ width: `${result.matchScore * 100}%` }}></div>
                    </div>
                    <span className="text-xs">{(result.matchScore * 100).toFixed(0)}%</span>
                  </TableCell>
                  <TableCell>{result.otherCharacteristics}</TableCell>
                </TableRow>
              ))
            }
          </TableBody>
        </Table>
      </div>
    </div>
  )
}

type ErrorBoundaryProps = {
  children: React.ReactNode;
}

type ErrorBoundaryState = {
  hasError: boolean;
}

class ErrorBoundary extends React.Component<ErrorBoundaryProps, ErrorBoundaryState> {
  constructor(props: ErrorBoundaryProps) {
    super(props);
    this.state = { hasError: false };
  }

  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  static getDerivedStateFromError(_error: Error): ErrorBoundaryState {
    return { hasError: true };
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="p-4 bg-red-50 border border-red-200 rounded-md">
          <h2 className="text-lg font-semibold text-red-700 mb-2">Something went wrong</h2>
          <p className="text-red-600">Please try refreshing the page or contact support if the problem persists.</p>
        </div>
      );
    }

    return this.props.children;
  }
}

export function SearchFormComponent() {
  const { register, control, handleSubmit } = useForm<FormData>()
  const [trials, setTrials] = useState<Trial[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [debugInfo, setDebugInfo] = useState<string | null>(null)
  const [selectedResults, setSelectedResults] = useState<PriorTrialResult[]>([])
  const [isComparisonOpen, setIsComparisonOpen] = useState(false)

  const onSubmit = async (data: FormData) => {
    setIsLoading(true)
    setError(null)
    setDebugInfo(null)

    // Map form field names to PHP expected names
    const formattedData = {
      ...data,
      mutations: data.mutations || '',
      biomarkers: data.biomarkers || '',
      previousSurgery: data.previousSurgery?.toLowerCase() || '',
      brainMetastases: data.brainMetastases?.toLowerCase() || '',
      cancerStage: data.cancerStage || '',
      metastaticCancer: data.metastaticCancer?.toLowerCase() || '',
      progressedPDL1: data.progressedPDL1?.toLowerCase() || '',
      specificResistances: data.specificResistances || '',
      priorDrugProgression: data.priorDrugProgression || '',
      priorDrugs: data.priorDrugs || '',
      treatmentNaive: data.treatmentNaive?.toLowerCase() || '',
      mutationMatchWeight: data.mutationMatchWeight || 12,
      MutationNotAllowed: data.mutationNotAllowedWeight || -12,
      mutationMentioned: data.mutationMentionedWeight || 3,
      mutationMentionedTitle: data.mutationMentionedTitleWeight || 3,
      surgeryMatchWeight: data.surgeryMatchWeight || 12,
      surgeryMismatchWeight: data.surgeryMismatchWeight || -12,
      biomarkerRequiredWeight: data.biomarkerRequiredWeight || 12,
      biomarkerRequiredMismatchWeight: data.biomarkerRequiredMismatchWeight || -12,
      biomarkerMentionedWeight: data.biomarkerMentionedWeight || 3,
      biomarkerNotAllowedWeight: data.biomarkerNotAllowedWeight || -12,
      biomarkerProgressionRequiredWeight: data.biomarkerProgressionRequiredWeight || 12,
      brainMetastasesMatchWeight: data.brainMetastasesMatchWeight || 12,
      brainMetastasesMismatchWeight: data.brainMetastasesMismatchWeight || -12,
      titleBrainMetastasesMatchWeight: data.titleBrainMetastasesMatchWeight || 12,
      titleBrainMetastasesMismatchWeight: data.titleBrainMetastasesMismatchWeight || -12,
      drugRequiredWeight: data.drugRequiredWeight || 12,
      drugNotAllowedWeight: data.drugNotAllowedWeight || -12,
      notAllowedBrainMetastasesMatchWeight: data.notAllowedBrainMetastasesMatchWeight || 12,
      notAllowedBrainMetastasesMismatchWeight: data.notAllowedBrainMetastasesMismatchWeight || -12,
      cancerStageMatchWeight: data.cancerStageMatchWeight || 12,
      metastaticCancerMatchWeight: data.metastaticCancerMatchWeight || 12,
      metastaticCancerMismatchWeight: data.metastaticCancerMismatchWeight || -12,
      pdl1ProgressionMatchWeight: data.pdl1ProgressionMatchWeight || 12,
      pdl1ProgressionMismatchWeight: data.pdl1ProgressionMismatchWeight || -12,
      resistanceRequiredMatchWeight: data.resistanceRequiredMatchWeight || 12,
      resistanceRequiredMismatchWeight: data.resistanceRequiredMismatchWeight || -12,
      resistanceSoughtMatchWeight: data.resistanceSoughtMatchWeight || 12,
      drugProgressionRequiredMatchWeight: data.drugProgressionRequiredMatchWeight || 12,
      drugProgressionRequiredMismatchWeight: data.drugProgressionRequiredMismatchWeight || -12,
      drugProgressionSoughtMatchWeight: data.drugProgressionSoughtMatchWeight || 12,
      treatmentNaiveRequiredMatchWeight: data.treatmentNaiveRequiredMatchWeight || 12,
      treatmentNaiveRequiredMismatchWeight: data.treatmentNaiveRequiredMismatchWeight || -12,
    };

    try {
      console.log('Form data:', formattedData)

      const response = await fetch('process_trials.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(formattedData)
      });

      const rawResponse = await response.text();
      console.log('Raw response:', rawResponse);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      let result: PHPResponse;
      try {
        result = JSON.parse(rawResponse);
      } catch (e) {
        console.error('JSON parse error:', e);
        throw new Error('Invalid JSON response from server');
      }

      // Transform the trials data to match our TypeScript interface
      const transformedTrials: Trial[] = (result.trials || []).map((trial: PHPTrialResponse) => ({
        NCTId: trial.NCTId,
        title: trial.BriefTitle || '',
        summary: trial.BriefSummary || '',
        score: trial.Score || 0,
        enrollment: typeof trial.Enrollment === 'string' ? 
          parseInt(trial.Enrollment) : (trial.Enrollment || 0),
        distance: trial.Distance,
        WithinRangeZips: trial.WithinRangeZips || [],
        enrollmentChange: trial.enrollmentChange || 0,
        scoringDetails: trial.ScoringDetails || [],
        priorTrialResults: (trial.priorTrialResults || []).map(result => ({
          id: result.id || String(Math.random()),
          drugNames: Array.isArray(result.drugNames) ? result.drugNames : [],
          pfs: typeof result.pfs === 'string' ? parseFloat(result.pfs) : (result.pfs || 0),
          orr: typeof result.orr === 'string' ? parseFloat(result.orr) : (result.orr || 0),
          os: typeof result.os === 'string' ? parseFloat(result.os) : (result.os || 0),
          genesMutations: Array.isArray(result.genesMutations) ? result.genesMutations : [],
          priorTreatments: Array.isArray(result.priorTreatments) ? result.priorTreatments : [],
          matchScore: typeof result.matchScore === 'string' ? 
            parseFloat(result.matchScore) : (result.matchScore || 0),
          otherCharacteristics: result.otherCharacteristics || ''
        })),
        LocationZip: trial.LocationZip || ''
      }));

      console.log('Transformed trials:', transformedTrials);
      setTrials(transformedTrials)
      setDebugInfo(JSON.stringify({ request: formattedData, response: result }, null, 2))
    } catch (error) {
      console.error('Fetch error:', error);
      setError('Failed to fetch trials. Please try again.');
    } finally {
      setIsLoading(false)
    }
  }

  const toggleResultSelection = (result: PriorTrialResult) => {
    setSelectedResults(prev => {
      const isSelected = prev.some(r => r.id === result.id);
      if (isSelected) {
        return prev.filter(r => r.id !== result.id);
      } else {
        return [...prev, result];
      }
    });
  }

  return (
    <div className="max-w-full mx-auto p-4 md:p-6">
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
        <Card className="bg-blue-50">
          <CardContent className="p-4 md:p-6">
            <h2 className="text-2xl md:text-3xl font-bold mb-4 md:mb-6 text-gray-900">Clinical Trial Search</h2>

            <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
              <div className="md:col-span-2 lg:col-span-3 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div className="relative">
                  <Label htmlFor="userLocation" className="mb-1 flex items-center">
                    Your Location
                    <InfoPopup description="Enter your ZIP code to find nearby clinical trials." />
                  </Label>
                  <Input
                    {...register('userLocation')}
                    placeholder="Enter ZIP Code"
                    id="userLocation"
                    className="w-full"
                  />
                </div>

                <div className="relative">
                  <Label htmlFor="maxDistance" className="mb-1 flex items-center">
                    Max Distance (miles)
                    <InfoPopup description="Specify the maximum distance you're willing to travel for a trial." />
                  </Label>
                  <Input
                    {...register('maxDistance')}
                    placeholder="Enter distance"
                    id="maxDistance"
                    type="number"
                    className="w-full"
                  />
                </div>

                <ExpandableSection 
                  inputProps={{
                    placeholder: "Enter prior drug treatments",
                    id: "priorDrugs"
                  }}
                  label="Prior Drug Treatments"
                  suggestions={[
                    "Chemotherapy", "Immunotherapy", "Targeted Therapy", "Hormone Therapy", "Radiation Therapy",
                    "Surgery", "Stem Cell Transplant", "CAR T-cell Therapy", "Angiogenesis Inhibitors",
                    "PARP Inhibitors", "Checkpoint Inhibitors", "Monoclonal Antibodies", "Tyrosine Kinase Inhibitors",
                    "Proteasome Inhibitors", "mTOR Inhibitors", "BRAF Inhibitors", "MEK Inhibitors", "ALK Inhibitors",
                    "EGFR Inhibitors", "CDK4/6 Inhibitors", "VEGF Inhibitors", "PD-1/PD-L1 Inhibitors", "CTLA-4 Inhibitors"
                  ]}
                  description="List any previous drug treatments you've received for your condition."
                  register={register}
                  name="priorDrugs"
                >
                  <WeightInput label="Drug Required Weight" name="drugRequiredWeight" control={control} />
                  <WeightInput label="Drug Not Allowed Weight" name="drugNotAllowedWeight" control={control} />
                </ExpandableSection>

                <ExpandableSection 
                  inputProps={{
                    placeholder: "Enter mutations",
                    id: "mutations"
                  }}
                  label="Mutations"
                  suggestions={[
                    "BRAF V600E", "EGFR T790M", "ALK Fusion", "KRAS G12C", "HER2 Amplification",
                    "BRCA1/2", "PIK3CA", "ROS1 Fusion", "NTRK Fusion", "MET Exon 14 Skipping",
                    "RET Fusion", "IDH1/2", "FGFR Alterations", "PTEN Loss", "TP53 Mutation",
                    "APC Mutation", "CDKN2A Loss", "SMAD4 Mutation", "STK11 Mutation", "KIT Mutation",
                    "JAK2 V617F", "FLT3 ITD", "NPM1 Mutation", "CEBPA Mutation", "IDH1 R132"
                  ]}
                  description="List any known genetic mutations related to your condition."
                  register={register}
                  name="mutations"
                >
                  <WeightInput label="Mutation Required Match Weight" name="mutationMatchWeight" control={control} />
                  <WeightInput label="Mutation Required Mismatch Weight" name="mutationMismatchWeight" control={control} />
                  <WeightInput label="Mutation Not Allowed Weight" name="mutationNotAllowedWeight" control={control} />
                  <WeightInput label="Mutation Mentioned Weight" name="mutationMentionedWeight" control={control} />
                  <WeightInput label="Mutation Mentioned in Title/Brief Weight" name="mutationMentionedTitleWeight" control={control} />
                </ExpandableSection>

                <ExpandableSection 
                  inputProps={{
                    placeholder: "Enter biomarkers",
                    id: "biomarkers"
                  }}
                  label="Biomarkers"
                  suggestions={[
                    "PD-L1", "HER2", "BRCA1/2", "MSI-H", "TMB-High", "EGFR", "ALK", "ROS1",
                    "BRAF", "KRAS", "NTRK", "MET", "RET", "PIK3CA", "IDH1/2", "FGFR",
                    "CD20", "VEGF", "ER/PR", "PSA", "CA-125", "CEA", "AFP", "CA 19-9",
                    "CD19", "CD22", "BCMA", "PSMA", "DLL3", "GD2", "Mesothelin", "NY-ESO-1"
                  ]}
                  description="List any relevant biomarkers associated with your condition."
                  register={register}
                  name="biomarkers"
                >
                  <WeightInput label="Biomarker Required Weight" name="biomarkerRequiredWeight" control={control} />
                  <WeightInput label="Biomarker Required Mismatch Weight" name="biomarkerRequiredMismatchWeight" control={control} />
                  <WeightInput label="Biomarker Not Allowed Weight" name="biomarkerNotAllowedWeight" control={control} />
                  <WeightInput label="Biomarker Mentioned Weight" name="biomarkerMentionedWeight" control={control} />
                  <WeightInput label="Biomarker Treatment Required" name="biomarkerProgressionRequiredWeight" control={control} />
                </ExpandableSection>

                <ExpandableSection 
                  inputProps={{
                    placeholder: "Enter specific resistances",
                    id: "specificResistances"
                  }}
                  label="Specific Resistances"
                  suggestions={[
                    "Drug resistance", "Treatment resistance", "Chemotherapy resistance", "Immunotherapy resistance",
                    "Targeted therapy resistance", "Hormone therapy resistance", "Radiation resistance",
                    "Multidrug resistance", "Platinum resistance", "EGFR TKI resistance", "ALK TKI resistance",
                    "BRAF inhibitor resistance", "Anti-HER2 therapy resistance", "Androgen deprivation therapy resistance",
                    "T790M mutation", "C797S mutation", "G1202R mutation", "Osimertinib resistance", "Crizotinib resistance",
                    "Cetuximab resistance", "Trastuzumab resistance", "Tamoxifen resistance", "Aromatase inhibitor resistance"
                  ]}
                  description="Specify any known resistances to treatments."
                  register={register}
                  name="specificResistances"
                >
                  <p>Specify any known resistances to treatments.</p>
                </ExpandableSection>

                <ExpandableSection
                  inputProps={{
                    placeholder: "Enter prior drug progression",
                    id: "priorDrugProgression"
                  }}
                  label="Prior Drug Progression"
                  suggestions={[
                    "Progressed on Chemotherapy", "Progressed on Immunotherapy", "Progressed on Targeted Therapy",
                    "Progressed on Hormone Therapy", "Progressed on Radiation Therapy", "Progressed on EGFR TKI",
                    "Progressed on ALK TKI", "Progressed on BRAF inhibitor", "Progressed on Anti-HER2 therapy",
                    "Progressed on PD-1/PD-L1 inhibitor", "Progressed on PARP inhibitor", "Progressed on CDK4/6 inhibitor",
                    "Progressed on mTOR inhibitor", "Progressed on Proteasome inhibitor", "Progressed on BTK inhibitor",
                    "Progressed on CAR T-cell therapy", "Progressed on Stem cell transplant", "Progressed on VEGF inhibitor",
                    "Progressed on CTLA-4 inhibitor", "Progressed on JAK inhibitor", "Progressed on PI3K inhibitor"
                  ]}
                  description="Indicate any progression on previous treatments."
                  register={register}
                  name="priorDrugProgression"
                >
                  <p>Indicate any progression on previous treatments.</p>
                </ExpandableSection>
              </div>

              <div className="md:col-span-1 space-y-4">
                <div className="relative">
                  <Label className="mb-1 flex items-center">
                    Treatment-Naive Patient
                    <InfoPopup description="Indicate whether you have received any previous treatment for your condition." />
                  </Label>
                  <div className="flex space-x-4">
                    <label className="inline-flex items-center">
                      <input
                        type="radio"
                        {...register('treatmentNaive')}
                        value="Yes"
                        className="form-radio text-primary"
                      />
                      <span className="ml-2">Yes</span>
                    </label>
                    <label className="inline-flex items-center">
                      <input
                        type="radio"
                        {...register('treatmentNaive')}
                        value="No"
                        className="form-radio text-primary"
                      />
                      <span className="ml-2">No</span>
                    </label>
                  </div>
                </div>

                <div className="relative">
                  <Label className="mb-1 flex items-center">
                    Previous Surgery
                    <InfoPopup description="Indicate if you have had any previous surgery related to your condition." />
                  </Label>
                  <Controller
                    name="previousSurgery"
                    control={control}
                    render={({ field }) => (
                      <Switch
                        checked={field.value === 'Yes'}
                        onChange={(checked) => field.onChange(checked ? 'Yes' : 'No')}
                        className={`${
                          field.value === 'Yes' ? 'bg-blue-600' : 'bg-gray-200'
                        } relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2`}
                      >
                        <span
                          className={`${
                            field.value === 'Yes' ? 'translate-x-6' : 'translate-x-1'
                          } inline-block h-4 w-4 transform rounded-full bg-white transition-transform`}
                        />
                      </Switch>
                    )}
                  />
                </div>

                <div className="relative">
                  <Label className="mb-1 flex items-center">
                    Brain Metastases Status
                    <InfoPopup description="Indicate if you have brain metastases." />
                  </Label>
                  <Controller
                    name="brainMetastases"
                    control={control}
                    render={({ field }) => (
                      <Switch
                        checked={field.value === 'Yes'}
                        onChange={(checked) => field.onChange(checked ? 'Yes' : 'No')}
                        className={`${
                          field.value === 'Yes' ? 'bg-blue-600' : 'bg-gray-200'
                        } relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2`}
                      >
                        <span
                          className={`${
                            field.value === 'Yes' ? 'translate-x-6' : 'translate-x-1'
                          } inline-block h-4 w-4 transform rounded-full bg-white transition-transform`}
                        />
                      </Switch>
                    )}
                  />
                </div>

                <div className="relative">
                  <Label className="mb-1 flex items-center">
                    Cancer Stage
                    <InfoPopup description="Select your cancer stage." />
                  </Label>
                  <div className="flex space-x-4">
                    {['1', '2', '3', '4'].map((stage) => (
                      <label key={stage} className="inline-flex items-center">
                        <input
                          type="radio"
                          {...register('cancerStage')}
                          value={stage}
                          className="form-radio text-primary"
                        />
                        <span className="ml-2">{stage}</span>
                      </label>
                    ))}
                  </div>
                </div>

                <div className="relative">
                  <Label className="mb-1 flex items-center">
                    Metastatic Cancer
                    <InfoPopup description="Indicate if your cancer has metastasized." />
                  </Label>
                  <Controller
                    name="metastaticCancer"
                    control={control}
                    render={({ field }) => (
                      <Switch
                        checked={field.value === 'Yes'}
                        onChange={(checked) => field.onChange(checked ? 'Yes' : 'No')}
                        className={`${
                          field.value === 'Yes' ? 'bg-blue-600' : 'bg-gray-200'
                        } relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2`}
                      >
                        <span
                          className={`${
                            field.value === 'Yes' ? 'translate-x-6' : 'translate-x-1'
                          } inline-block h-4 w-4 transform rounded-full bg-white transition-transform`}
                        />
                      </Switch>
                    )}
                  />
                </div>

                <div className="relative">
                  <Label className="mb-1 flex items-center">
                    Progressed on PD-1/PD-L1 Treatment
                    <InfoPopup description="Indicate if you have progressed on PD-1/PD-L1 treatment." />
                  </Label>
                  <Controller
                    name="progressedPDL1"
                    control={control}
                    render={({ field }) => (
                      <Switch
                        checked={field.value === 'Yes'}
                        onChange={(checked) => field.onChange(checked ? 'Yes' : 'No')}
                        className={`${
                          field.value === 'Yes' ? 'bg-blue-600' : 'bg-gray-200'
                        } relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2`}
                      >
                        <span
                          className={`${
                            field.value === 'Yes' ? 'translate-x-6' : 'translate-x-1'
                          } inline-block h-4 w-4 transform rounded-full bg-white transition-transform`}
                        />
                      </Switch>
                    )}
                  />
                </div>

                <Button
                  type="submit"
                  disabled={isLoading}
                  className="w-full bg-blue-600 hover:bg-blue-700 text-white mt-4"
                >
                  {isLoading ? 'Searching...' : 'Search'}
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      </form>

      {error && (
        <div className="mt-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded" role="alert">
          <p>{error}</p>
        </div>
      )}

      {trials.length > 0 && (
        <div className="mt-6">
          <h3 className="text-2xl font-bold mb-4">Search Results</h3>
          <div className="space-y-6">
            {trials.map((trial) => (
              <Card key={trial.NCTId}>
                <CardContent className="p-6">
                  <h4 className="text-xl font-semibold mb-2">
                    <a href={`https://clinicaltrials.gov/ct2/show/${trial.NCTId}`} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:text-blue-800">
                      {trial.title}
                    </a>
                  </h4>
                  <p className="text-gray-600 mb-4">{trial.summary}</p>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <p className="font-semibold">Score: <span className="font-normal">{trial.score}</span></p>
                      <p className="font-semibold">Enrollment: <span className="font-normal">{trial.enrollment}</span></p>
                      {trial.distance && <p className="font-semibold">Distance: <span className="font-normal">{trial.distance} miles</span></p>}
                    </div>
                    <div>
                      <p className="font-semibold">Zip Codes: <span className="font-normal">
                        {trial.WithinRangeZips ? trial.WithinRangeZips.join(', ') : 'No locations available'}
                      </span></p>
                                            <p className="font-semibold">
                        Enrollment Change: 
                        <span className={`font-normal ${trial.enrollmentChange > 0 ? 'text-green-600' : 'text-red-600'}`}>
                          {trial.enrollmentChange > 0 ? '+' : ''}{trial.enrollmentChange}
                                                </span>
                      </p>
                    </div>
                  </div>
                  {trial.scoringDetails && trial.scoringDetails.length > 0 && (
                    <div className="mt-4">
                      <h5 className="font-semibold mb-2">Scoring Details:</h5>
                      <ul className="list-disc list-inside">
                        {trial.scoringDetails.map((detail, index) => (
                          <li key={index}>
                            {detail.criterion}: {detail.score}
                          </li>
                        ))}
                      </ul>
                    </div>
                  )}
                  {trial.priorTrialResults && trial.priorTrialResults.length > 0 && (
                    <Disclosure>
                      {({ open }) => (
                        <>
                          <Disclosure.Button className="flex justify-between w-full px-4 py-2                         mt-4 text-sm font-medium text-left text-blue-900 bg-blue-100 rounded-lg hover:bg-blue-200 focus:outline-none focus-visible:ring focus-visible:ring-blue-500 focus-visible:ring-opacity-75">
                            <span>Prior Clinical Trial Results ({trial.priorTrialResults.length})</span>
                            <ChevronUpIcon
                              className={`${
                                open ? 'transform rotate-180' : ''
                              } w-5 h-5 text-blue-500`}
                            />
                          </Disclosure.Button>
                          <Disclosure.Panel className="px-4 pt-4 pb-2 text-sm text-gray-500">
                            <Table>
                              <TableHeader>
                                <TableRow>
                                  <TableHead>Add to Comparison</TableHead>
                                  <TableHead>Drug Name(s)</TableHead>
                                  <TableHead>PFS</TableHead>
                                  <TableHead>ORR</TableHead>
                                  <TableHead>OS</TableHead>
                                  <TableHead>Genes/Mutations</TableHead>
                                  <TableHead>Prior Treatments</TableHead>
                                  <TableHead>Match Score</TableHead>
                                  <TableHead>Other Characteristics</TableHead>
                                </TableRow>
                              </TableHeader>
                              <TableBody>
                                {trial.priorTrialResults.map((result) => (
                                  <TableRow key={result.id}>
                                    <TableCell>
                                      <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => toggleResultSelection(result)}
                                        aria-label="Add to comparison"
                                      >
                                        {selectedResults.some(r => r.id === result.id) ? (
                                          <CheckIcon className="h-4 w-4" />
                                        ) : (
                                          <PlusIcon className="h-4 w-4" />
                                        )}
                                      </Button>
                                    </TableCell>
                                    <TableCell>{result.drugNames.join(', ')}</TableCell>
                                    <TableCell>{result.pfs.toFixed(1)} months</TableCell>
                                    <TableCell>{(result.orr * 100).toFixed(1)}%</TableCell>
                                    <TableCell>{result.os.toFixed(1)} months</TableCell>
                                    <TableCell>{result.genesMutations.join(', ')}</TableCell>
                                    <TableCell>{result.priorTreatments.join(', ')}</TableCell>
                                    <TableCell>
                                      <div className="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                        <div className="bg-blue-600 h-2.5 rounded-full" style={{ width: `${result.matchScore * 100}%` }}></div>
                                      </div>
                                      <span className="text-xs">{(result.matchScore * 100).toFixed(0)}%</span>
                                    </TableCell>
                                    <TableCell>{result.otherCharacteristics}</TableCell>
                                  </TableRow>
                                ))}
                              </TableBody>
                            </Table>
                          </Disclosure.Panel>
                        </>
                      )}
                    </Disclosure>
                  )}
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      )}

      {debugInfo && (
        <div className="mt-6">
          <h3 className="text-xl font-bold mb-2">Debug Information</h3>
          <pre className="bg-gray-100 p-4 rounded-lg overflow-x-auto">
            {debugInfo}
          </pre>
        </div>
      )}

      {/* Comparison Panel for Desktop */}
      <AnimatePresence>
        {isComparisonOpen && (
          <motion.div
            initial={{ opacity: 0, x: '100%' }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: '100%' }}
            transition={{ type: 'spring', stiffness: 300, damping: 30 }}
            className="fixed top-0 right-0 w-2/3 h-full bg-white shadow-lg overflow-y-auto z-50 md:block"
          >
            <ComparisonPanel
              selectedResults={selectedResults}
              onClose={() => setIsComparisonOpen(false)}
            />
          </motion.div>
        )}
      </AnimatePresence>

      {/* Comparison Sheet for Mobile */}
      <Sheet open={isComparisonOpen} onOpenChange={setIsComparisonOpen}>
        <SheetContent side="bottom" className="h-[80vh] overflow-y-auto md:hidden p-0">
          <SheetHeader className="p-4 bg-white">
            <SheetTitle>Compare Clinical Trial Results</SheetTitle>
          </SheetHeader>
          <div className="bg-white h-full">
            <ComparisonPanel
              selectedResults={selectedResults}
              onClose={() => setIsComparisonOpen(false)}
            />
          </div>
        </SheetContent>
      </Sheet>

      {/* Floating Compare Button */}
      {selectedResults.length > 0 && (
        <Button
          onClick={() => setIsComparisonOpen(true)}
          className="fixed bottom-4 right-4 bg-blue-600 hover:bg-blue-700 text-white"
        >
          Compare Trial Results ({selectedResults.length})
        </Button>
      )}
    </div>
  )
}

// Wrap the export with ErrorBoundary
export default function SearchFormWithErrorBoundary() {
  return (
    <ErrorBoundary>
      <SearchFormComponent />
    </ErrorBoundary>
  );
}