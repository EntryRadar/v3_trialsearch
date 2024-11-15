<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// At the beginning of the script
$debugInfo = [];

// Function to parse CSV file and return an associative array
function parseCsv($filePath) {
    global $debugInfo; // Ensure $debugInfo is accessible within the function
    if (!file_exists($filePath)) {
        $debugInfo[] = "CSV file not found at: $filePath";
        return [];
    }

    $handle = fopen($filePath, 'r');
    if (!$handle) {
        $debugInfo[] = "Failed to open CSV file at: $filePath";
        return [];
    }

    $headers = fgetcsv($handle); // Read the first line as headers
    $array = [];
    while (($row = fgetcsv($handle)) !== false) {
        $rowArray = [];
        foreach ($headers as $index => $header) {
            $rowArray[$header] = $row[$index] ?? null;
        }
        $array[] = $rowArray;
        // Optionally unset $rowArray here if memory issues persist
        unset($rowArray);
    }
    fclose($handle);

    $debugInfo[] = "CSV parsed successfully. Number of rows: " . count($array);
    return $array;
}

// Helper function to format the score with a sign
function formatScore($score) {
    return ($score >= 0 ? '+' : '') . $score;
}

// Scoring function
function scoreTrial($trial, $params, &$debugInfo, $userLat, $userLng, $maxDistance, $zipData) {
    $score = 0;
    $details = [];
    
    if (!function_exists('safeCheck')) {
        function safeCheck($trial, $key, $value) {
            $synonyms = loadSynonyms();
            $field = str_replace('Query', '', explode('-', $key)[0]);
            
            if (isset($synonyms[$field])) {
                foreach ($synonyms[$field] as $mainTerm => $synonymTerms) {
                    if ($value === $mainTerm || in_array($value, $synonymTerms)) {
                        $allTerms = array_merge([$mainTerm], $synonymTerms);
                        foreach ($allTerms as $term) {
                            if (isset($trial[$key]) && stripos($trial[$key], $term) !== false) {
                                return true;
                            }
                        }
                    }
                }
            }
            
            return isset($trial[$key]) && stripos($trial[$key], $value) !== false;
        }
    }

    // Use provided weights or default values
    $mutationMatchWeight = isset($params['mutationMatchWeight']) ? (int)$params['mutationMatchWeight'] : 12;
    $mutationMentioned = isset($params['mutationMentioned']) ? (int)$params['mutationMentioned'] : 3;
    $mutationMentionedTitle = isset($params['mutationMentionedTitle']) ? (int)$params['mutationMentionedTitle'] : 3;
    $MutationNotAllowed = isset($params['MutationNotAllowed']) ? (int)$params['MutationNotAllowed'] : -12;
    $mutationMismatchWeight = isset($params['mutationMismatchWeight']) ? (int)$params['mutationMismatchWeight'] : -5;
    $surgeryMatchWeight = isset($params['surgeryMatchWeight']) ? (int)$params['surgeryMatchWeight'] : 5;
    $surgeryMismatchWeight = isset($params['surgeryMismatchWeight']) ? (int)$params['surgeryMismatchWeight'] : -10;
    $BiomarkerRequired = isset($params['BiomarkerRequired']) ? (int)$params['BiomarkerRequired'] : -10;
    // Biomarker Default Weights
    $biomarkerRequiredWeight = isset($params['biomarkerRequiredWeight']) ? (int)$params['biomarkerRequiredWeight'] : 12;
    $biomarkerRequiredMismatchWeight = isset($params['biomarkerRequiredMismatchWeight']) ? (int)$params['biomarkerRequiredMismatchWeight'] : 12;
    $biomarkerMentioned = isset($params['biomarkerMentioned']) ? (int)$params['biomarkerMentioned'] : 3;
    $biomarkerNotAllowed = isset($params['biomarkerNotAllowed']) ? (int)$params['biomarkerNotAllowed'] : -12;
    $biomarkerProgressionRequired = isset($params['biomarkerProgressionRequired']) ? (int)$params['biomarkerProgressionRequired'] : 7;
    
    // Metastases Complex Weights
    $brainMetastasesMatchWeight = isset($params['brainMetastasesMatchWeight']) ? (int)$params['brainMetastasesMatchWeight'] : -12;
    $brainMetastasesMismatchWeight = isset($params['brainMetastasesMismatchWeight']) ? (int)$params['brainMetastasesMismatchWeight'] : 7;
    $titlebrainMetastasesMatchWeight = isset($params['titlebrainMetastasesMatchWeight']) ? (int)$params['titlebrainMetastasesMatchWeight'] : -12;
    $titlebrainMetastasesMismatchWeight = isset($params['titlebrainMetastasesMismatchWeight']) ? (int)$params['titlebrainMetastasesMismatchWeight'] : 7;
    
    // Prior Drug Treatment Weights
    $drugRequiredWeight = isset($params['drugRequiredWeight']) ? (int)$params['drugRequiredWeight'] : 10;
    $drugNotAllowedWeight = isset($params['drugNotAllowedWeight']) ? (int)$params['drugNotAllowedWeight'] : -10;
    
    // Define new weights for brain metastases logic
    $notAllowedbrainMetastasesMatchWeight = -12; // Adjust this value as needed
    $notAllowedbrainMetastasesMismatchWeight = 1; // Adjust this value as needed

    // Define new weight for cancer stage match
    $cancerStageMatchWeight = isset($params['cancerStageMatchWeight']) ? (int)$params['cancerStageMatchWeight'] : 5;

    // Define new weights for metastatic cancer logic
    $metastaticCancerMatchWeight = isset($params['metastaticCancerMatchWeight']) ? (int)$params['metastaticCancerMatchWeight'] : 10;
    $metastaticCancerMismatchWeight = isset($params['metastaticCancerMismatchWeight']) ? (int)$params['metastaticCancerMismatchWeight'] : -10;

    // Define new weights for PD-1/PD-L1 progression logic
    $pdl1ProgressionMatchWeight = isset($params['pdl1ProgressionMatchWeight']) ? (int)$params['pdl1ProgressionMatchWeight'] : 10;
    $pdl1ProgressionMismatchWeight = isset($params['pdl1ProgressionMismatchWeight']) ? (int)$params['pdl1ProgressionMismatchWeight'] : -10;

    // Define new weights for specific resistances logic
    $resistanceRequiredMatchWeight = isset($params['resistanceRequiredMatchWeight']) ? (int)$params['resistanceRequiredMatchWeight'] : 10;
    $resistanceRequiredMismatchWeight = isset($params['resistanceRequiredMismatchWeight']) ? (int)$params['resistanceRequiredMismatchWeight'] : -5;
    $resistanceSoughtMatchWeight = isset($params['resistanceSoughtMatchWeight']) ? (int)$params['resistanceSoughtMatchWeight'] : 5;

    // Define new weights for drug progression logic
    $drugProgressionRequiredMatchWeight = isset($params['drugProgressionRequiredMatchWeight']) ? (int)$params['drugProgressionRequiredMatchWeight'] : 10;
    $drugProgressionRequiredMismatchWeight = isset($params['drugProgressionRequiredMismatchWeight']) ? (int)$params['drugProgressionRequiredMismatchWeight'] : -5;
    $drugProgressionSoughtMatchWeight = isset($params['drugProgressionSoughtMatchWeight']) ? (int)$params['drugProgressionSoughtMatchWeight'] : 5;

    // Define new weights for treatment-naive patient logic
    $treatmentNaiveRequiredMatchWeight = isset($params['treatmentNaiveRequiredMatchWeight']) ? (int)$params['treatmentNaiveRequiredMatchWeight'] : 10;
    $treatmentNaiveRequiredMismatchWeight = isset($params['treatmentNaiveRequiredMismatchWeight']) ? (int)$params['treatmentNaiveRequiredMismatchWeight'] : -10;

    // Define scoring rules based on URL parameters
    // Mutations
    if (isset($params['mutations']) && !empty(trim($params['mutations']))) {
        $mutations = explode(',', $params['mutations']);
        foreach ($mutations as $mutation) {
            $mutation = trim($mutation); // Trim each mutation to remove whitespace
            if (!empty($mutation)) { // Check if mutation is not empty
                // Log each mutation check
                $debugInfo[] = "Checking mutation: $mutation";
                if (safeCheck($trial, 'Query2-V1', $mutation)) {
                    $score += $mutationMatchWeight;
                    $details[] = "Mutation is Required to Participate ($mutation): " . formatScore($mutationMatchWeight);
                }
                if (safeCheck($trial, 'Query1-V1', $mutation)) {
                    $score += $mutationMentioned;
                    $details[] = "Mutation is Specifically Mentioned ($mutation): " . formatScore($mutationMentioned);
                }
                if (safeCheck($trial, 'Query3-V1', $mutation)) {
                    $score += $mutationMentionedTitle;
                    $details[] = "Mutation is Specifically Mentioned in the Title/Brief ($mutation): " . formatScore($mutationMentionedTitle);
                }
                if (safeCheck($trial, 'Query4-V1', $mutation)) {
                    $score += $MutationNotAllowed;
                    $details[] = "Mutation is Not Allowed in Trial ($mutation): " . formatScore($MutationNotAllowed);
                }
            }
        }
    }
    //Biomarker Rules

    if (isset($params['biomarkers']) && !empty(trim($params['biomarkers']))) {
        $biomarkers = explode(',', $params['biomarkers']);
        foreach ($biomarkers as $biomarker) {
            $biomarker = trim($biomarker); // Trim each biomarker to remove whitespace
            if (!empty($biomarker)) { // Check if biomarker is not empty
                if (safeCheck($trial, 'Query6-V1', $biomarker)) {
                    $score += $biomarkerRequiredWeight;
                    $details[] = "Biomarker is Required to participate ($biomarker): " . formatScore($biomarkerRequiredWeight);
                }
                if (safeCheck($trial, 'Query7-V1', $biomarker)) {
                    $score += $biomarkerMentioned;
                    $details[] = "Biomarker is Specifically Mentioned ($biomarker): " . formatScore($biomarkerMentioned);
                }
                if (safeCheck($trial, 'Query8-V1', $biomarker)) {
                    $score += $biomarkerMentionedTitle;
                    $details[] = "Biomarker is Specifically Mentioned in the Title/Brief ($biomarker): " . formatScore($biomarkerMentionedTitle);
                }
                if (safeCheck($trial, 'Query9-V1', $biomarker)) {
                    $score += $biomarkerNotAllowed;
                    $details[] = "Biomarker is Not Allowed to participate ($biomarker): " . formatScore($biomarkerNotAllowed);
                }
                if (safeCheck($trial, 'Query9-V1', $biomarker)) {
                    $score += $biomarkerProgressionRequired;
                    $details[] = "Biomarker requires previous treatment to participate ($biomarker): " . formatScore($biomarkerProgressionRequired);
                }
            }
        }
    }
    // Previous Surgery
    if (isset($params['previousSurgery']) && isset($trial['Query16-V1'])) {
        $requiredSurgery = stripos($trial['Query16-V1'], "yes") !== false;
        $hasSurgery = stripos($params['previousSurgery'], "yes") !== false;
        
        if ($requiredSurgery) {
            if ($hasSurgery) {
                $score += $surgeryMatchWeight;
                $details[] = "Previous surgery requirement matched: " . formatScore($surgeryMatchWeight);
            } else {
                $score -= $surgeryMismatchWeight;
                $details[] = "Lack of required previous surgery: " . formatScore(-$surgeryMismatchWeight);
            }
        } else {
            // When surgery is not required, no points are added or subtracted
            // regardless of whether the patient has had surgery or not.
            // You can optionally add details for clarification.
            $details[] = "No surgery requirement: +0";
        }
    }

    // Brain Metastases
    if (isset($params['brainMetastases'])) {
        if (isset($trial['Query17-V1'])) {
            $requireBrainMetastases = stripos($trial['Query17-V1'], "yes") !== false;
            $hasbrainMetastases = stripos($params['brainMetastases'], "yes") !== false;
        
            if ($requireBrainMetastases) {
                if ($hasbrainMetastases) {
                    $score += $brainMetastasesMatchWeight;
                    $details[] = "Brain Metastases requirement matched: " . formatScore($brainMetastasesMatchWeight);
                } else {
                    $score -= $brainMetastasesMismatchWeight;
                    $details[] = "Lack of required Brain Metastases: " . formatScore(-$brainMetastasesMismatchWeight);
                }
            } else {
                $details[] = "No Brain Metastases requirement: +0";
            }
        }
        
        if (isset($trial['Query18-V1'])) {
            $titleBrainMetastases = stripos($trial['Query18-V1'], "yes") !== false;
            $hasbrainMetastases = stripos($params['brainMetastases'], "yes") !== false;
        
            if ($titleBrainMetastases) {
                if ($hasbrainMetastases) {
                    $score += $titlebrainMetastasesMatchWeight;
                    $details[] = "Trial Focuses on Brain Metastases: " . formatScore($titlebrainMetastasesMatchWeight);
                } else {
                    $score -= $titlebrainMetastasesMismatchWeight;
                    $details[] = "Trial Focuses on Brain Metastases (But May or May Not Be Required): " . formatScore(-$titlebrainMetastasesMismatchWeight);
                }
            } else {
                $details[] = "No Brain Metastases requirement: +0";
            }
        }
        if (isset($trial['Query19-V1'])) {
            $titleBrainMetastases = stripos($trial['Query19-V1'], "yes") !== false;
            $hasbrainMetastases = stripos($params['brainMetastases'], "yes") !== false;
        
            if ($titleBrainMetastases) {
                if ($hasbrainMetastases) {
                    $score += $notAllowedbrainMetastasesMatchWeight;
                    $details[] = "Trial Does Not Allow Brain Metastases and Patient Has Brain Metastases: " . formatScore($notAllowedbrainMetastasesMatchWeight); // Match would mean that the Trial does not allow for Brain Metastases and the patient has Brain Metastases
                } else {
                    $score -= $notAllowedbrainMetastasesMismatchWeight;
                    $details[] = "Trial Does not Allow Brain Metastases and Patient Does Not Have Brain Metastases: " . formatScore(-$notAllowedbrainMetastasesMismatchWeight); //
                }
            } else {
                $details[] = "Brain Metastases Not Disallowed: +0";
            }
        }
    }

    // Cancer Stage Matching Logic
    if (isset($params['cancerStage']) && isset($trial['Query20-V1'])) {
        $inputStage = trim($params['cancerStage']);
        $trialStages = strtolower($trial['Query20-V1']);

        // Debug information
        $debugInfo[] = "Input Cancer Stage: Stage " . $inputStage;
        $debugInfo[] = "Trial Stages: " . $trialStages;

        if (stripos($trialStages, $inputStage) !== false) {
            $score += $cancerStageMatchWeight;
            $details[] = "Cancer stage found in requirements (Stage $inputStage): " . formatScore($cancerStageMatchWeight);
            $debugInfo[] = "Cancer stage match found. Score increased by " . formatScore($cancerStageMatchWeight);
        } else {
            $debugInfo[] = "No cancer stage match found.";
        }
    }

    // Prior Drug Treatment
    if (isset($params['priorDrugs']) && !empty(trim($params['priorDrugs']))) {
        $priorDrugs = explode(',', $params['priorDrugs']);
        foreach ($priorDrugs as $priorDrug) {
            $priorDrug = trim($priorDrug); // Trim each drug to remove whitespace
            if (!empty($priorDrug)) { // Check if drug is not empty
                if (safeCheck($trial, 'Query25-V1', $priorDrug) || safeCheck($trial, 'Query26-V1', $priorDrug)) {
                    $score += $drugNotAllowedWeight;
                    $details[] = "Prior drug use is not allowed to participate in Trial ($priorDrug): " . formatScore($drugNotAllowedWeight);
                }
                if (safeCheck($trial, 'Query12-V1', $priorDrug)) {
                    $score += $drugRequiredWeight;
                    $details[] = "Prior drug use is required to participate in Trial ($priorDrug): " . formatScore($drugRequiredWeight);
                }
            }
        }
    }

    // Metastatic Cancer
    if (isset($params['metastaticCancer']) && isset($trial['Query23-V1'])) {
        $requireMetastaticCancer = stripos($trial['Query23-V1'], "yes") !== false;
        $hasMetastaticCancer = stripos($params['metastaticCancer'], "yes") !== false;
    
        if ($requireMetastaticCancer) {
            if ($hasMetastaticCancer) {
                $score += $metastaticCancerMatchWeight;
                $details[] = "Metastatic cancer requirement matched: " . formatScore($metastaticCancerMatchWeight);
            } else {
                $score -= $metastaticCancerMismatchWeight;
                $details[] = "Lack of required metastatic cancer: " . formatScore(-$metastaticCancerMismatchWeight);
            }
        } else {
            $details[] = "No metastatic cancer requirement: +0";
        }
    }

    // PD-1/PD-L1 Progression
    if (isset($params['progressedPDL1']) && isset($trial['Query27-R1'])) {
        $requirePDL1Progression = stripos($trial['Query27-R1'], "yes") !== false;
        $hasProgressedPDL1 = stripos($params['progressedPDL1'], "yes") !== false;
    
        if ($requirePDL1Progression) {
            if ($hasProgressedPDL1) {
                $score += $pdl1ProgressionMatchWeight;
                $details[] = "PD-1/PD-L1 progression requirement matched: " . formatScore($pdl1ProgressionMatchWeight);
            } else {
                $score -= $pdl1ProgressionMismatchWeight;
                $details[] = "Lack of required PD-1/PD-L1 progression: " . formatScore(-$pdl1ProgressionMismatchWeight);
            }
        } else {
            $details[] = "No PD-1/PD-L1 progression requirement: +0";
        }
    }

    // Specific Resistances
    if (isset($params['specificResistances']) && !empty(trim($params['specificResistances']))) {
        $resistances = explode(',', $params['specificResistances']);
        foreach ($resistances as $resistance) {
            $resistance = trim($resistance);
            if (!empty($resistance)) {
                // Check for required resistances (Query31-V1)
                if (safeCheck($trial, 'Query31-V1', $resistance)) {
                    $score += $resistanceRequiredMatchWeight;
                    $details[] = "Specific resistance required and matched ($resistance): " . formatScore($resistanceRequiredMatchWeight);
                } else {
                    $score += $resistanceRequiredMismatchWeight;
                    $details[] = "Specific resistance required but not matched ($resistance): " . formatScore($resistanceRequiredMismatchWeight);
                }
                
                // Check for sought resistances (Query30-V1)
                if (safeCheck($trial, 'Query30-V1', $resistance)) {
                    $score += $resistanceSoughtMatchWeight;
                    $details[] = "Specific resistance sought and matched ($resistance): " . formatScore($resistanceSoughtMatchWeight);
                }
            }
        }
    }

    // Drug Progression
    if (isset($params['priorDrugProgression']) && !empty(trim($params['priorDrugProgression']))) {
        $priorDrugs = explode(',', $params['priorDrugProgression']);
        $patientDrugs = array_map('trim', $priorDrugs);

        // Check for required drug progression (Query33-V1)
        $requiredDrugs = explode(',', $trial['Query33-V1']);
        $requiredDrugs = array_map('trim', $requiredDrugs);

        foreach ($requiredDrugs as $requiredDrug) {
            if (!empty($requiredDrug) && $requiredDrug !== 'None') {
                if (in_array($requiredDrug, $patientDrugs)) {
                    $score += $drugProgressionRequiredMatchWeight;
                    $details[] = "Drug progression required and matched ($requiredDrug): " . formatScore($drugProgressionRequiredMatchWeight);
                } else {
                    $score += $drugProgressionRequiredMismatchWeight;
                    $details[] = "Drug progression required but not matched ($requiredDrug): " . formatScore($drugProgressionRequiredMismatchWeight);
                }
            }
        }
        
        // Check for drug progression if taken (Query32-V1 and Query34-V1)
        $soughtDrugs = array_merge(
            explode(',', $trial['Query32-V1']),
            explode(',', $trial['Query34-V1'])
        );
        $soughtDrugs = array_map('trim', $soughtDrugs);

        foreach ($soughtDrugs as $soughtDrug) {
            if (!empty($soughtDrug) && $soughtDrug !== 'None') {
                if (in_array($soughtDrug, $patientDrugs)) {
                    $score += $drugProgressionSoughtMatchWeight;
                    $details[] = "Drug progression sought and matched ($soughtDrug): " . formatScore($drugProgressionSoughtMatchWeight);
                }
            }
        }
    }

    // Treatment-Naive Patient
    if (isset($params['treatmentNaive']) && isset($trial['Query35-V1'])) {
        $requireTreatmentNaive = stripos($trial['Query35-V1'], "yes") !== false;
        $isTreatmentNaive = stripos($params['treatmentNaive'], "yes") !== false;

        if ($requireTreatmentNaive) {
            if ($isTreatmentNaive) {
                $score += $treatmentNaiveRequiredMatchWeight;
                $details[] = "Treatment-naive requirement matched: " . formatScore($treatmentNaiveRequiredMatchWeight);
            } else {
                $score += $treatmentNaiveRequiredMismatchWeight;
                $details[] = "Treatment-naive requirement not met: " . formatScore($treatmentNaiveRequiredMismatchWeight);
            }
        } else {
            $details[] = "No treatment-naive requirement: +0";
        }
    }

    // Calculate distance for each trial location only if user coordinates are provided
    $closestDistance = PHP_FLOAT_MAX;
    $withinRangeZips = [];
    if ($userLat !== null && $userLng !== null) {
        $trialLocations = explode('|', $trial['LocationZip']);
        foreach ($trialLocations as $trialLocation) {
            if (isset($zipData[$trialLocation])) {
                $trialLat = floatval($zipData[$trialLocation]['lat']);
                $trialLng = floatval($zipData[$trialLocation]['lng']);
                $distance = calculateDistance($userLat, $userLng, $trialLat, $trialLng);
                if ($distance <= $maxDistance) {
                    $withinRangeZips[] = $trialLocation;
                }
                $closestDistance = min($closestDistance, $distance);
            }
        }
        $debugInfo[] = "Trial ID: " . $trial['NCTId'] . ", Closest Distance: " . round($closestDistance, 2) . " miles, Zips within range: " . implode(", ", $withinRangeZips);
    } else {
        // If no user location provided, consider all trial locations as within range
        $withinRangeZips = explode('|', $trial['LocationZip']);
        $closestDistance = 0;
        $debugInfo[] = "Trial ID: " . $trial['NCTId'] . ", No user location provided, all locations considered within range.";
    }

    // Log final score for the trial
    $debugInfo[] = "Final score for trial: " . $score;
    return ['Score' => $score, 'Details' => $details, 'Distance' => $closestDistance, 'WithinRangeZips' => $withinRangeZips];
}

// Function to load zip code data
function loadZipData($filePath) {
    $zipData = [];
    if (($handle = fopen($filePath, "r")) !== FALSE) {
        // Skip the header row
        fgetcsv($handle, 1000, ",");
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $zipData[$data[0]] = ['lat' => $data[1], 'lng' => $data[2]];
        }
        fclose($handle);
    }
    return $zipData;
}

// Function to calculate distance between two points using Haversine formula
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    // Check if any of the inputs are null
    if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
        return PHP_FLOAT_MAX; // Return a very large number if we can't calculate the distance
    }

    $earthRadius = 3959; // in miles
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}

// Function to get coordinates from user location
function getCoordinatesFromLocation($location, $zipData) {
    if (is_numeric($location) && isset($zipData[$location])) {
        // If user entered a zip code
        return [
            'lat' => floatval($zipData[$location]['lat']),
            'lng' => floatval($zipData[$location]['lng'])
        ];
    } else {
        // If user entered a city name, use Google Maps Geocoding API
        $apiKey = 'AIzaSyC6CbbxJcQ60AnpSxzCLgAqT9uOihh-Izk';
        $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($location) . "&key=" . $apiKey;
        
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        
        if ($data['status'] === 'OK') {
            $lat = $data['results'][0]['geometry']['location']['lat'];
            $lng = $data['results'][0]['geometry']['location']['lng'];
            
            // Find the closest zip code
            $closestZip = findClosestZipCode($lat, $lng, $zipData);
            
            return [
                'lat' => floatval($lat),
                'lng' => floatval($lng),
                'zip' => $closestZip
            ];
        } else {
            // Return default coordinates if geocoding fails
            return [
                'lat' => 0,
                'lng' => 0,
                'zip' => ''
            ];
        }
    }
}

function findClosestZipCode($lat, $lng, $zipData) {
    $closestZip = '';
    $minDistance = PHP_FLOAT_MAX;
    
    foreach ($zipData as $zip => $coords) {
        $distance = calculateDistance($lat, $lng, $coords['lat'], $coords['lng']);
        if ($distance < $minDistance) {
            $minDistance = $distance;
            $closestZip = $zip;
        }
    }
    
    return $closestZip;
}

// Function to print zip codes within max distance range (for debugging)
function printZipCodesWithinRange($zipData, $userLat, $userLng, $maxDistance) {
    $zipCodesWithinRange = [];
    foreach ($zipData as $zipCode => $coords) {
        $distance = calculateDistance($userLat, $userLng, $coords['lat'], $coords['lng']);
        if ($distance <= $maxDistance) {
            $zipCodesWithinRange[] = $zipCode;
        }
    }
    return $zipCodesWithinRange;
}

// Simple tokenizer function
function simpleTokenizer($text) {
    return preg_split('/\s+/', trim($text));
}

// Function to get common key phrases
function getCommonOptions($trials, $field, $min_n = 1, $max_n = 3) {
    $allPhrases = [];
    foreach ($trials as $trial) {
        if (isset($trial[$field])) {
            $text = $trial[$field];
            $ngrams = generateNGrams($text, $min_n, $max_n);
            foreach ($ngrams as $ngram) {
                if (!isset($allPhrases[$ngram])) {
                    $allPhrases[$ngram] = 0;
                }
                $allPhrases[$ngram]++;
            }
        }
    }
    arsort($allPhrases);
    return array_keys($allPhrases);
}

function generateNGrams($text, $min_n, $max_n) {
    $words = explode(' ', $text);
    $ngrams = [];
    for ($n = $min_n; $n <= $max_n; $n++) {
        for ($i = 0; $i < count($words) - $n + 1; $i++) {
            $ngram = implode(' ', array_slice($words, $i, $n));
            $ngrams[] = $ngram;
        }
    }
    return $ngrams;
}

function consolidateSynonyms($phrases) {
    $consolidated = [];
    $used = [];

    foreach ($phrases as $phrase) {
        if (!in_array($phrase, $used)) {
            $group = [$phrase];
            foreach ($phrases as $other) {
                if (!in_array($other, $used) && fuzz.partial_ratio($phrase, $other) > 80) {
                    $group[] = $other;
                    $used[] = $other;
                }
            }
            $consolidated[$phrase] = $group;
        }
    }
    return $consolidated;
}

// Function to get cached options
function getCachedOptions($forceRegenerate = false) {
    $cacheFile = __DIR__ . '/options_cache.json';
    $cacheDir = dirname($cacheFile);

    // Ensure cache directory exists and is writable
    if (!is_dir($cacheDir)) {
        if (!mkdir($cacheDir, 0755, true)) {
            error_log("Failed to create cache directory: $cacheDir");
            return null;
        }
    }

    if (!is_writable($cacheDir)) {
        error_log("Cache directory is not writable: $cacheDir");
        return null;
    }

    if (!$forceRegenerate && file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if ($cache !== null) {
            return $cache;
        } else {
            error_log("Failed to decode cache file: $cacheFile");
        }
    }

    // Regenerate cache
    $filePath = 'v3.csv';
    $trials = parseCsv($filePath);
    if (empty($trials)) {
        error_log("No trials data found in CSV file: $filePath");
        return null;
    }

    $options = [
        'mutations' => consolidateSynonyms(getCommonOptions($trials, 'Query2-V1')),
        'biomarkers' => consolidateSynonyms(getCommonOptions($trials, 'Query6-V1')),
        'specificResistances' => consolidateSynonyms(getCommonOptions($trials, 'Query31-V1')),
        'priorDrugProgression' => consolidateSynonyms(getCommonOptions($trials, 'Query33-V1')),
        'priorDrugs' => consolidateSynonyms(getCommonOptions($trials, 'Query25-V1'))
    ];

    $jsonOptions = json_encode($options);
    if ($jsonOptions === false) {
        error_log("Failed to encode options as JSON");
        return null;
    }

    $result = file_put_contents($cacheFile, $jsonOptions);
    if ($result === false) {
        error_log("Failed to write cache file: $cacheFile");
        return null;
    }

    return $options;
}

function loadSynonyms() {
    $synonymsFile = 'synonyms.json';
    if (file_exists($synonymsFile)) {
        return json_decode(file_get_contents($synonymsFile), true);
    }
    return [];
}

// Main logic
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'getOptions') {
        $forceRegenerate = isset($_GET['forceRegenerate']) && $_GET['forceRegenerate'] === 'true';
        $cachedOptions = getCachedOptions($forceRegenerate);
        if ($cachedOptions === null) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to generate or retrieve options']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['options' => $cachedOptions, 'synonyms' => loadSynonyms()]);
        }
        exit;
    }

    $filePath = 'v3.csv';
    $trials = parseCsv($filePath);
    $params = $_GET;

    $debugInfo[] = "Received parameters: " . print_r($params, true);
    $debugInfo[] = "Number of trials loaded: " . count($trials);

    // Get user location and max distance
    $userLocation = $params['userLocation'] ?? '';
    $maxDistance = isset($params['maxDistance']) ? floatval($params['maxDistance']) : PHP_FLOAT_MAX;

    // Load zip code data
    $zipDataFilePath = 'us_zip_codes.csv';
    $zipData = loadZipData($zipDataFilePath);

    // Initialize user coordinates
    $userLat = null;
    $userLng = null;

    // Only get user coordinates if a location is provided
    if (!empty($userLocation)) {
        $userCoords = getCoordinatesFromLocation($userLocation, $zipData);
        $userLat = $userCoords['lat'];
        $userLng = $userCoords['lng'];

        // Print zip codes within max distance range (for debugging)
        $zipCodesWithinRange = printZipCodesWithinRange($zipData, $userLat, $userLng, $maxDistance);
        $debugInfo[] = "Zip codes within max distance range: " . implode(", ", $zipCodesWithinRange);
    }

    // Filter and score trials
    $filteredTrials = [];
    foreach ($trials as $trial) {
        $trialScore = scoreTrial($trial, $params, $debugInfo, $userLat, $userLng, $maxDistance, $zipData);
        $score = $trialScore['Score'];
        $details = $trialScore['Details'];
        $closestDistance = $trialScore['Distance'];
        $withinRangeZips = $trialScore['WithinRangeZips'];

        // Add trial to filtered list if no location is provided or if it's within range
        if (empty($userLocation) || !empty($withinRangeZips)) {
            $trial['Distance'] = $closestDistance;
            $trial['Score'] = $score;
            $trial['ScoringDetails'] = $details;
            $trial['WithinRangeZips'] = $withinRangeZips;
            $filteredTrials[] = $trial;
        }
    }

    // Sort filtered trials by score (descending)
    usort($filteredTrials, function($a, $b) {
        return $b['Score'] - $a['Score'];
    });

    $debugInfo[] = "Number of filtered trials: " . count($filteredTrials);

    // Prepare response
    $response = [
        'trials' => $filteredTrials,
        'debug' => $debugInfo
    ];

    // Send response as JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>




