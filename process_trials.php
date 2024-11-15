<?php
// Prevent PHP from outputting warnings directly
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Create error handler to capture warnings
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    global $debugInfo;
    $debugInfo[] = "Warning: $errstr in $errfile on line $errline";
    return true;
});

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Initialize debug info array
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
    // Initialize all weight parameters with default values
    $weights = [
        'mutationMatchWeight' => 12,
        'mutationMentioned' => 3,
        'mutationMentionedTitle' => 3,
        'MutationNotAllowed' => -12,
        'mutationMismatchWeight' => -5,
        'surgeryMatchWeight' => 5,
        'surgeryMismatchWeight' => -10,
        'BiomarkerRequired' => -10,
        'biomarkerRequiredWeight' => 12,
        'biomarkerRequiredMismatchWeight' => 12,
        'biomarkerMentioned' => 3,
        'biomarkerNotAllowed' => -12,
        'biomarkerProgressionRequired' => 7,
        'brainMetastasesMatchWeight' => 5,
        'brainMetastasesMismatchWeight' => -10,
        'titlebrainMetastasesMatchWeight' => -12,
        'titlebrainMetastasesMismatchWeight' => 7,
        'drugRequiredWeight' => 10,
        'drugNotAllowedWeight' => -10,
        'notAllowedbrainMetastasesMatchWeight' => -12,
        'notAllowedbrainMetastasesMismatchWeight' => 1,
        'cancerStageMatchWeight' => 5,
        'metastaticCancerMatchWeight' => 10,
        'metastaticCancerMismatchWeight' => -10,
        'pdl1ProgressionMatchWeight' => 10,
        'pdl1ProgressionMismatchWeight' => -10,
        'resistanceRequiredMatchWeight' => 10,
        'resistanceRequiredMismatchWeight' => -5,
        'resistanceSoughtMatchWeight' => 5,
        'drugProgressionRequiredMatchWeight' => 10,
        'drugProgressionRequiredMismatchWeight' => -5,
        'drugProgressionSoughtMatchWeight' => 5,
        'treatmentNaiveRequiredMatchWeight' => 10,
        'treatmentNaiveRequiredMismatchWeight' => -10
    ];

    // Override defaults with provided weights
    foreach ($weights as $key => $defaultValue) {
        $weights[$key] = isset($params[$key]) ? (int)$params[$key] : $defaultValue;
    }

    // Use $weights instead of direct $params access
    $score = 0;
    $details = [];

    if (!function_exists('safeCheck')) {
        function safeCheck($trial, $key, $value) {
            $synonyms = loadSynonyms();
            $field = str_replace('Query', '', explode('-', $key)[0]);
            
            // Convert both strings to uppercase for case-insensitive comparison
            $trialValue = isset($trial[$key]) ? strtoupper($trial[$key]) : '';
            $searchValue = strtoupper($value);
            
            // Check direct match first
            if (strpos($trialValue, $searchValue) !== false) {
                return true;
            }
            
            // Check synonyms if available
            if (isset($synonyms[$field])) {
                foreach ($synonyms[$field] as $mainTerm => $synonymTerms) {
                    // Convert mainTerm to uppercase
                    $mainTerm = strtoupper($mainTerm);
                    if ($searchValue === $mainTerm || in_array($searchValue, array_map('strtoupper', $synonymTerms))) {
                        $allTerms = array_merge([$mainTerm], array_map('strtoupper', $synonymTerms));
                        foreach ($allTerms as $term) {
                            if (strpos($trialValue, $term) !== false) {
                                return true;
                            }
                        }
                    }
                }
            }
            
            return false;
        }
    }

    // Mutations
    if (isset($params['mutations']) && !empty(trim($params['mutations']))) {
        $mutations = explode(',', $params['mutations']);
        foreach ($mutations as $mutation) {
            $mutation = trim($mutation);
            if (!empty($mutation)) {
                $debugInfo[] = "Checking mutation: $mutation";
                if (safeCheck($trial, 'Query2-FUA8', $mutation)) {
                    $score += $weights['mutationMatchWeight'];
                    $details[] = "Mutation is Required to Participate ($mutation): " . formatScore($weights['mutationMatchWeight']);
                }
                if (safeCheck($trial, 'Query1-FUA8', $mutation)) {
                    $score += $weights['mutationMentioned'];
                    $details[] = "Mutation is Specifically Mentioned ($mutation): " . formatScore($weights['mutationMentioned']);
                }
                if (safeCheck($trial, 'Query3-FUA8', $mutation)) {
                    $score += $weights['mutationMentionedTitle'];
                    $details[] = "Mutation is Specifically Mentioned in the Title/Brief ($mutation): " . formatScore($weights['mutationMentionedTitle']);
                    $debugInfo[] = "Found mutation '$mutation' in title/brief: " . $trial['BriefTitle'];
                } else {
                    $debugInfo[] = "Did not find mutation '$mutation' in title/brief: " . $trial['BriefTitle'];
                }
                if (safeCheck($trial, 'Query4-FUA8', $mutation)) {
                    $score += $weights['MutationNotAllowed'];
                    $details[] = "Mutation is Not Allowed in Trial ($mutation): " . formatScore($weights['MutationNotAllowed']);
                }
            }
        }
    }

    // Biomarkers
    if (isset($params['biomarkers']) && !empty(trim($params['biomarkers']))) {
        $biomarkers = explode(',', $params['biomarkers']);
        foreach ($biomarkers as $biomarker) {
            $biomarker = trim($biomarker);
            if (!empty($biomarker)) {
                if (safeCheck($trial, 'Query6-FUA8', $biomarker)) {
                    $score += $weights['biomarkerRequiredWeight'];
                    $details[] = "Biomarker is Required to participate ($biomarker): " . formatScore($weights['biomarkerRequiredWeight']);
                }
                if (safeCheck($trial, 'Query7-FUA8', $biomarker)) {
                    $score += $weights['biomarkerMentioned'];
                    $details[] = "Biomarker is Specifically Mentioned ($biomarker): " . formatScore($weights['biomarkerMentioned']);
                }
                if (safeCheck($trial, 'Query8-FUA8', $biomarker)) {
                    $score += $weights['biomarkerMentionedTitle'];
                    $details[] = "Biomarker is Specifically Mentioned in the Title/Brief ($biomarker): " . formatScore($weights['biomarkerMentionedTitle']);
                }
                if (safeCheck($trial, 'Query9-FUA8', $biomarker)) {
                    $score += $weights['biomarkerNotAllowed'];
                    $details[] = "Biomarker is Not Allowed to participate ($biomarker): " . formatScore($weights['biomarkerNotAllowed']);
                }
                if (safeCheck($trial, 'Query9-FUA8', $biomarker)) {
                    $score += $weights['biomarkerProgressionRequired'];
                    $details[] = "Biomarker requires previous treatment to participate ($biomarker): " . formatScore($weights['biomarkerProgressionRequired']);
                }
            }
        }
    }

    // Previous Surgery
    if (isset($params['previousSurgery']) && isset($trial['Query16-FUA8'])) {
        $requiredSurgery = stripos($trial['Query16-FUA8'], "yes") !== false;
        $hasSurgery = stripos($params['previousSurgery'], "yes") !== false;
        
        if ($requiredSurgery) {
            if ($hasSurgery) {
                $score += $weights['surgeryMatchWeight'];
                $details[] = "Previous surgery requirement matched: " . formatScore($weights['surgeryMatchWeight']);
            } else {
                $score += $weights['surgeryMismatchWeight'];
                $details[] = "Lack of required previous surgery: " . formatScore($weights['surgeryMismatchWeight']);
            }
        } else {
            $details[] = "No surgery requirement: +0";
        }
    }

    // Brain Metastases
    if (isset($params['brainMetastases'])) {
        if (isset($trial['Query17-FUA8'])) {
            $requireBrainMetastases = stripos($trial['Query17-FUA8'], "yes") !== false;
            $hasbrainMetastases = stripos($params['brainMetastases'], "yes") !== false;
        
            if ($requireBrainMetastases) {
                if ($hasbrainMetastases) {
                    $score += $weights['brainMetastasesMatchWeight'];
                    $details[] = "Brain Metastases requirement matched: " . formatScore($weights['brainMetastasesMatchWeight']);
                } else {
                    $score += $weights['brainMetastasesMismatchWeight'];
                    $details[] = "Lack of required Brain Metastases: " . formatScore($weights['brainMetastasesMismatchWeight']);
                }
            }
        }
        
        if (isset($trial['Query18-FUA8'])) {
            $titleBrainMetastases = stripos($trial['Query18-FUA8'], "yes") !== false;
            // Only proceed if brainMetastases parameter is set and not empty
            if (isset($params['brainMetastases']) && !empty($params['brainMetastases'])) {
                $hasbrainMetastases = stripos($params['brainMetastases'], "yes") !== false;
            
                if ($titleBrainMetastases) {
                    if ($hasbrainMetastases) {
                        $score += $weights['titlebrainMetastasesMatchWeight'];
                        $details[] = "Trial Focuses on Brain Metastases: " . formatScore($weights['titlebrainMetastasesMatchWeight']);
                    } else {
                        $score += $weights['titlebrainMetastasesMismatchWeight'];
                        $details[] = "Trial Focuses on Brain Metastases (But May or May Not Be Required): " . formatScore($weights['titlebrainMetastasesMismatchWeight']);
                    }
                }
            }
        }
        if (isset($trial['Query19-FUA8'])) {
            $titleBrainMetastases = stripos($trial['Query19-FUA8'], "yes") !== false;
            $hasbrainMetastases = stripos($params['brainMetastases'], "yes") !== false;
        
            if ($titleBrainMetastases) {
                if ($hasbrainMetastases) {
                    $score += $weights['notAllowedbrainMetastasesMatchWeight'];
                    $details[] = "Trial Does Not Allow Brain Metastases and Patient Has Brain Metastases: " . formatScore($weights['notAllowedbrainMetastasesMatchWeight']); // Match would mean that the Trial does not allow for Brain Metastases and the patient has Brain Metastases
                } else {
                    $score += $weights['notAllowedbrainMetastasesMismatchWeight'];
                    $details[] = "Trial Does not Allow Brain Metastases and Patient Does Not Have Brain Metastases: " . formatScore($weights['notAllowedbrainMetastasesMismatchWeight']); //
                }
            } else {
                $details[] = "Brain Metastases Not Disallowed: +0";
            }
        }
    }

    // Cancer Stage Matching Logic
    if (isset($params['cancerStage']) && isset($trial['Query20-FUA8'])) {
        $inputStage = trim($params['cancerStage']);
        $trialStages = strtolower($trial['Query20-FUA8']);

        // Debug information
        $debugInfo[] = "Input Cancer Stage: Stage " . $inputStage;
        $debugInfo[] = "Trial Stages: " . $trialStages;

        if (stripos($trialStages, $inputStage) !== false) {
            $score += $weights['cancerStageMatchWeight'];
            $details[] = "Cancer stage found in requirements (Stage $inputStage): " . formatScore($weights['cancerStageMatchWeight']);
            $debugInfo[] = "Cancer stage match found. Score increased by " . formatScore($weights['cancerStageMatchWeight']);
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
                if (safeCheck($trial, 'Query25-FUA8', $priorDrug) || safeCheck($trial, 'Query26-FUA8', $priorDrug)) {
                    $score += $weights['drugNotAllowedWeight'];
                    $details[] = "Prior drug use is not allowed to participate in Trial ($priorDrug): " . formatScore($weights['drugNotAllowedWeight']);
                }
                if (safeCheck($trial, 'Query12-FUA8', $priorDrug)) {
                    $score += $weights['drugRequiredWeight'];
                    $details[] = "Prior drug use is required to participate in Trial ($priorDrug): " . formatScore($weights['drugRequiredWeight']);
                }
            }
        }
    }

    // Metastatic Cancer
    if (isset($params['metastaticCancer']) && isset($trial['Query23-FUA8'])) {
        $requireMetastaticCancer = stripos($trial['Query23-FUA8'], "yes") !== false;
        $hasMetastaticCancer = stripos($params['metastaticCancer'], "yes") !== false;
    
        if ($requireMetastaticCancer) {
            if ($hasMetastaticCancer) {
                $score += $weights['metastaticCancerMatchWeight'];
                $details[] = "Metastatic cancer requirement matched: " . formatScore($weights['metastaticCancerMatchWeight']);
            } else {
                $score += $weights['metastaticCancerMismatchWeight'];
                $details[] = "Lack of required metastatic cancer: " . formatScore($weights['metastaticCancerMismatchWeight']);
            }
        } else {
            $details[] = "No metastatic cancer requirement: +0";
        }
    }

    // PD-1/PD-L1 Progression
    if (isset($params['progressedPDL1']) && isset($trial['Query27-FUA8'])) {
        $requirePDL1Progression = stripos($trial['Query27-FUA8'], "yes") !== false;
        $hasProgressedPDL1 = stripos($params['progressedPDL1'], "yes") !== false;
    
        if ($requirePDL1Progression) {
            if ($hasProgressedPDL1) {
                $score += $weights['pdl1ProgressionMatchWeight'];
                $details[] = "PD-1/PD-L1 progression requirement matched: " . formatScore($weights['pdl1ProgressionMatchWeight']);
            } else {
                $score += $weights['pdl1ProgressionMismatchWeight'];
                $details[] = "Lack of required PD-1/PD-L1 progression: " . formatScore($weights['pdl1ProgressionMismatchWeight']);
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
                // Check for required resistances (Query31-FUA8)
                if (safeCheck($trial, 'Query31-FUA8', $resistance)) {
                    $score += $weights['resistanceRequiredMatchWeight'];
                    $details[] = "Specific resistance required and matched ($resistance): " . formatScore($weights['resistanceRequiredMatchWeight']);
                } else {
                    $score += $weights['resistanceRequiredMismatchWeight'];
                    $details[] = "Specific resistance required but not matched ($resistance): " . formatScore($weights['resistanceRequiredMismatchWeight']);
                }
                
                // Check for sought resistances (Query30-FUA8)
                if (safeCheck($trial, 'Query30-FUA8', $resistance)) {
                    $score += $weights['resistanceSoughtMatchWeight'];
                    $details[] = "Specific resistance sought and matched ($resistance): " . formatScore($weights['resistanceSoughtMatchWeight']);
                }
            }
        }
    }

    // Drug Progression
    if (isset($params['priorDrugProgression']) && !empty(trim($params['priorDrugProgression']))) {
        $priorDrugs = explode(',', $params['priorDrugProgression']);
        $patientDrugs = array_map('trim', $priorDrugs);

        // Check for required drug progression (Query33-FUA8)
        $requiredDrugs = explode(',', $trial['Query33-FUA8']);
        $requiredDrugs = array_map('trim', $requiredDrugs);

        foreach ($requiredDrugs as $requiredDrug) {
            if (!empty($requiredDrug) && $requiredDrug !== 'None') {
                if (in_array($requiredDrug, $patientDrugs)) {
                    $score += $weights['drugProgressionRequiredMatchWeight'];
                    $details[] = "Drug progression required and matched ($requiredDrug): " . formatScore($weights['drugProgressionRequiredMatchWeight']);
                } else {
                    $score += $weights['drugProgressionRequiredMismatchWeight'];
                    $details[] = "Drug progression required but not matched ($requiredDrug): " . formatScore($weights['drugProgressionRequiredMismatchWeight']);
                }
            }
        }
        
        // Check for drug progression if taken (Query32-FUA8 and Query34-FUA8)
        $soughtDrugs = array_merge(
            explode(',', $trial['Query32-FUA8']),
            explode(',', $trial['Query34-FUA8'])
        );
        $soughtDrugs = array_map('trim', $soughtDrugs);

        foreach ($soughtDrugs as $soughtDrug) {
            if (!empty($soughtDrug) && $soughtDrug !== 'None') {
                if (in_array($soughtDrug, $patientDrugs)) {
                    $score += $weights['drugProgressionSoughtMatchWeight'];
                    $details[] = "Drug progression sought and matched ($soughtDrug): " . formatScore($weights['drugProgressionSoughtMatchWeight']);
                }
            }
        }
    }

    // Treatment-Naive Patient
    if (isset($params['treatmentNaive']) && isset($trial['Query35-FUA8'])) {
        $requireTreatmentNaive = stripos($trial['Query35-FUA8'], "yes") !== false;
        $isTreatmentNaive = stripos($params['treatmentNaive'], "yes") !== false;

        if ($requireTreatmentNaive) {
            if ($isTreatmentNaive) {
                $score += $weights['treatmentNaiveRequiredMatchWeight'];
                $details[] = "Treatment-naive requirement matched: " . formatScore($weights['treatmentNaiveRequiredMatchWeight']);
            } else {
                $score += $weights['treatmentNaiveRequiredMismatchWeight'];
                $details[] = "Treatment-naive requirement not met: " . formatScore($weights['treatmentNaiveRequiredMismatchWeight']);
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
    return [
        'Score' => $score,
        'Details' => $details,
        'Distance' => $closestDistance ?? null,
        'WithinRangeZips' => $withinRangeZips ?? []
    ];
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
        'mutations' => consolidateSynonyms(getCommonOptions($trials, 'Query2-FUA8')),
        'biomarkers' => consolidateSynonyms(getCommonOptions($trials, 'Query6-FUA8')),
        'specificResistances' => consolidateSynonyms(getCommonOptions($trials, 'Query31-FUA8')),
        'priorDrugProgression' => consolidateSynonyms(getCommonOptions($trials, 'Query33-FUA8')),
        'priorDrugs' => consolidateSynonyms(getCommonOptions($trials, 'Query25-FUA8'))
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

// Main logic - MOVE THIS TO THE END OF THE FILE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON data from POST request
        $json = file_get_contents('php://input');
        $params = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data');
        }

        // Normalize parameters to match old version's expectations
        $params = array_merge([
            'mutations' => '',
            'biomarkers' => '',
            'previousSurgery' => '2',
            'brainMetastases' => '',
            'cancerStage' => '',
            'metastaticCancer' => '',
            'progressedPDL1' => '',
            'specificResistances' => '',
            'priorDrugProgression' => '',
            'priorDrugs' => '',
            'treatmentNaive' => '',
            // Add default weights
            'mutationMatchWeight' => 12,
            'mutationMentioned' => 3,
            'mutationMentionedTitle' => 3,
            'MutationNotAllowed' => -12,
            'biomarkerRequiredWeight' => 12,
            // ... other default weights
        ], $params);

        $filePath = __DIR__ . '/v3.csv';
        $trials = parseCsv($filePath);

        $debugInfo[] = "Received parameters: " . print_r($params, true);
        $debugInfo[] = "Number of trials loaded: " . count($trials);

        // Get user location and max distance
        $userLocation = $params['userLocation'] ?? '';
        $maxDistance = isset($params['maxDistance']) ? floatval($params['maxDistance']) : PHP_FLOAT_MAX;

        // Load zip code data
        $zipDataFilePath = __DIR__ . '/us_zip_codes.csv';
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
                // Transform scoring details into the expected format
                $formattedDetails = array_map(function($detail) {
                    // Split the detail string into criterion and score
                    $parts = explode(':', $detail);
                    $criterion = trim($parts[0]);
                    $score = isset($parts[1]) ? (int)filter_var($parts[1], FILTER_SANITIZE_NUMBER_INT) : 0;
                    
                    return [
                        'criterion' => $criterion,
                        'score' => $score
                    ];
                }, $details);

                $trial['LocationZip'] = $trial['LocationZip'] ?? '';
                $trial['Distance'] = $closestDistance;
                $trial['Score'] = $score;
                $trial['ScoringDetails'] = $formattedDetails;
                $trial['WithinRangeZips'] = $withinRangeZips ?: [];
                // Add default values for other required fields
                $trial['enrollmentChange'] = $trial['enrollmentChange'] ?? 0;
                $trial['priorTrialResults'] = $trial['priorTrialResults'] ?? [];
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
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => $e->getMessage(),
            'debug' => $debugInfo
        ]);
    }
    exit;
} else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'getOptions') {
    $forceRegenerate = isset($_GET['forceRegenerate']) && $_GET['forceRegenerate'] === 'true';
    $cachedOptions = getCachedOptions($forceRegenerate);
    if ($cachedOptions === null) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate or retrieve options']);
    } else {
        echo json_encode(['options' => $cachedOptions, 'synonyms' => loadSynonyms()]);
    }
    exit;
} else if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Handle preflight requests
    http_response_code(200);
    exit;
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
?>




