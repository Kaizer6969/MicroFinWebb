<?php
require_once __DIR__ . '/api_utils.php';
require_once __DIR__ . '/db.php';

microfin_api_bootstrap();
microfin_require_post();

$data = microfin_read_json_input();

$userId = (int) ($data['user_id'] ?? 0);
$tenantId = microfin_clean_string($data['tenant_id'] ?? '');
$phoneNumber = microfin_clean_string($data['phone_number'] ?? '');
$fullName = microfin_clean_string($data['full_name'] ?? '');
$dateOfBirth = microfin_clean_string($data['date_of_birth'] ?? '');
$gender = microfin_clean_string($data['gender'] ?? '');
$civilStatus = microfin_clean_string($data['civil_status'] ?? '');
$employmentStatus = microfin_clean_string($data['employment_status'] ?? '');
$occupation = microfin_clean_string($data['occupation'] ?? '');
$employerName = microfin_clean_string($data['employer'] ?? '');
$employerContact = microfin_clean_string($data['employer_contact'] ?? '');
$monthlyIncome = (float) ($data['monthly_income'] ?? 0);
$houseNo = microfin_clean_string($data['house_no'] ?? '');
$street = microfin_clean_string($data['street'] ?? '');
$barangay = microfin_clean_string($data['barangay'] ?? '');
$city = microfin_clean_string($data['city'] ?? '');
$province = microfin_clean_string($data['province'] ?? '');
$postal = microfin_clean_string($data['postal'] ?? '');
$sameAsPermanent = microfin_clean_string($data['same_as_permanent'] ?? '0') === '1' ? 1 : 0;
$permHouseNo = microfin_clean_string($data['perm_house_no'] ?? '');
$permStreet = microfin_clean_string($data['perm_street'] ?? '');
$permBarangay = microfin_clean_string($data['perm_barangay'] ?? '');
$permCity = microfin_clean_string($data['perm_city'] ?? '');
$permProvince = microfin_clean_string($data['perm_province'] ?? '');
$permPostal = microfin_clean_string($data['perm_postal'] ?? '');
$hasComaker = microfin_clean_string($data['has_comaker'] ?? '0') === '1' ? 1 : 0;
$comakerName = microfin_clean_string($data['comaker_name'] ?? '');
$comakerRelationship = microfin_clean_string($data['comaker_relationship'] ?? '');
$comakerContact = microfin_clean_string($data['comaker_contact'] ?? '');
$comakerIncome = (float) ($data['comaker_income'] ?? 0);
$comakerAddress = microfin_clean_string($data['comaker_address'] ?? '');
$idType = microfin_clean_string($data['id_type'] ?? '');
$idNumber = microfin_clean_string($data['id_number'] ?? '');
$idExpiry = microfin_clean_string($data['id_expiry'] ?? '');
$documents = $data['documents'] ?? [];

if ($userId <= 0 || $tenantId === '') {
    microfin_json_response(['success' => false, 'message' => 'Missing user or tenant context.'], 422);
}

if ($phoneNumber === '' || $fullName === '' || $dateOfBirth === '' || $idType === '') {
    microfin_json_response(['success' => false, 'message' => 'Please complete the required verification details.'], 422);
}

if (!is_array($documents) || count($documents) === 0) {
    microfin_json_response(['success' => false, 'message' => 'Please upload your ID and supporting documents first.'], 422);
}

function microfin_has_client_column(mysqli $conn, string $column): bool
{
    static $cache = [];

    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'clients'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");

    if (!$stmt) {
        $cache[$column] = false;
        return false;
    }

    $stmt->bind_param('s', $column);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows === 1;
    $stmt->close();

    $cache[$column] = $exists;
    return $exists;
}

function microfin_split_full_name(string $fullName): array
{
    $parts = preg_split('/\s+/', trim($fullName)) ?: [];
    $parts = array_values(array_filter($parts, static fn ($value) => $value !== ''));

    if (count($parts) === 0) {
        return ['first_name' => '', 'middle_name' => '', 'last_name' => ''];
    }

    if (count($parts) === 1) {
        return ['first_name' => $parts[0], 'middle_name' => '', 'last_name' => $parts[0]];
    }

    $firstName = array_shift($parts);
    $lastName = array_pop($parts);
    $middleName = implode(' ', $parts);

    return [
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'last_name' => $lastName,
    ];
}

function microfin_is_valid_date(?string $value): bool
{
    if ($value === null || trim($value) === '') {
        return false;
    }

    $date = date_create(trim($value));
    return $date instanceof DateTime;
}

function microfin_normalize_date(?string $value): ?string
{
    if (!microfin_is_valid_date($value)) {
        return null;
    }

    return date('Y-m-d', strtotime((string) $value));
}

function microfin_build_document_note(array $data, int $documentTypeId): string
{
    $notes = ['Submitted from the mobile verification flow.'];

    if ($documentTypeId === 21) {
        $idType = microfin_clean_string($data['id_type'] ?? '');
        $idNumber = microfin_clean_string($data['id_number'] ?? '');
        $idExtractedName = microfin_clean_string($data['id_extracted_name'] ?? '');
        $idExtractedDob = microfin_clean_string($data['id_extracted_dob'] ?? '');
        $idExtractedAddress = microfin_clean_string($data['id_extracted_address'] ?? '');

        if ($idType !== '') {
            $notes[] = 'ID type: ' . $idType . '.';
        }
        if ($idNumber !== '') {
            $notes[] = 'Document number: ' . $idNumber . '.';
        }
        if ($idExtractedName !== '') {
            $notes[] = 'OCR name: ' . $idExtractedName . '.';
        }
        if ($idExtractedDob !== '') {
            $notes[] = 'OCR DOB: ' . $idExtractedDob . '.';
        }
        if ($idExtractedAddress !== '') {
            $notes[] = 'OCR address: ' . $idExtractedAddress . '.';
        }
    }

    return trim(implode(' ', $notes));
}

$parsedName = microfin_split_full_name($fullName);
$birthDate = microfin_normalize_date($dateOfBirth);
$expiryDate = microfin_normalize_date($idExpiry);

if ($birthDate === null) {
    microfin_json_response(['success' => false, 'message' => 'Date of birth must use a valid date format.'], 422);
}

try {
    $clientStmt = $conn->prepare("
        SELECT
            c.client_id,
            c.first_name,
            c.middle_name,
            c.last_name,
            u.email
        FROM clients c
        INNER JOIN users u
            ON u.user_id = c.user_id
           AND u.tenant_id = c.tenant_id
        WHERE c.user_id = ?
          AND c.tenant_id = ?
          AND c.deleted_at IS NULL
          AND u.deleted_at IS NULL
        LIMIT 1
    ");

    if (!$clientStmt) {
        throw new RuntimeException('Failed to prepare client lookup.');
    }

    $clientStmt->bind_param('is', $userId, $tenantId);
    $clientStmt->execute();
    $clientResult = $clientStmt->get_result();
    $client = $clientResult->fetch_assoc();
    $clientStmt->close();

    if (!$client) {
        microfin_json_response(['success' => false, 'message' => 'Client profile not found for this account.'], 404);
    }

    $firstName = $parsedName['first_name'] !== '' ? $parsedName['first_name'] : (string) ($client['first_name'] ?? '');
    $middleName = $parsedName['middle_name'];
    $lastName = $parsedName['last_name'] !== '' ? $parsedName['last_name'] : (string) ($client['last_name'] ?? '');
    $emailAddress = microfin_clean_string($client['email'] ?? '');

    $docTypeStmt = $conn->prepare("
        SELECT document_type_id
        FROM document_types
        WHERE document_type_id = ?
          AND is_active = 1
        LIMIT 1
    ");
    if (!$docTypeStmt) {
        throw new RuntimeException('Failed to prepare document type lookup.');
    }

    $existingDocStmt = $conn->prepare("
        SELECT client_document_id
        FROM client_documents
        WHERE client_id = ?
          AND tenant_id = ?
          AND document_type_id = ?
        ORDER BY upload_date DESC, client_document_id DESC
        LIMIT 1
    ");
    if (!$existingDocStmt) {
        throw new RuntimeException('Failed to prepare existing document lookup.');
    }

    $insertDocStmt = $conn->prepare("
        INSERT INTO client_documents (
            client_id,
            tenant_id,
            document_type_id,
            file_name,
            file_path,
            document_number,
            file_size,
            file_type,
            verification_status,
            verification_notes,
            expiry_date,
            is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, 1)
    ");
    if (!$insertDocStmt) {
        throw new RuntimeException('Failed to prepare document insert.');
    }

    $updateDocStmt = $conn->prepare("
        UPDATE client_documents
        SET file_name = ?,
            file_path = ?,
            document_number = ?,
            file_size = ?,
            file_type = ?,
            upload_date = NOW(),
            verification_status = 'Pending',
            verification_notes = ?,
            expiry_date = ?,
            is_active = 1
        WHERE client_document_id = ?
    ");
    if (!$updateDocStmt) {
        throw new RuntimeException('Failed to prepare document update.');
    }

    $conn->begin_transaction();

    $userStmt = $conn->prepare("
        UPDATE users
        SET phone_number = ?,
            first_name = ?,
            middle_name = ?,
            last_name = ?,
            date_of_birth = ?,
            updated_at = NOW()
        WHERE user_id = ?
          AND tenant_id = ?
        LIMIT 1
    ");
    if (!$userStmt) {
        throw new RuntimeException('Failed to prepare user update.');
    }

    $userStmt->bind_param(
        'sssssiss',
        $phoneNumber,
        $firstName,
        $middleName,
        $lastName,
        $birthDate,
        $userId,
        $tenantId
    );
    $userStmt->execute();
    $userStmt->close();

    $clientUpdateSql = "
        UPDATE clients
        SET first_name = ?,
            middle_name = ?,
            last_name = ?,
            date_of_birth = ?,
            gender = ?,
            civil_status = ?,
            contact_number = ?,
            email_address = ?,
            present_house_no = ?,
            present_street = ?,
            present_barangay = ?,
            present_city = ?,
            present_province = ?,
            present_postal_code = ?,
            permanent_house_no = ?,
            permanent_street = ?,
            permanent_barangay = ?,
            permanent_city = ?,
            permanent_province = ?,
            permanent_postal_code = ?,
            same_as_present = ?,
            employment_status = ?,
            occupation = ?,
            employer_name = ?,
            employer_contact = ?,
            monthly_income = ?,
            comaker_name = ?,
            comaker_relationship = ?,
            comaker_contact = ?,
            comaker_income = ?,
            comaker_street = ?,
            id_type = ?,
            verification_rejection_reason = NULL,
            updated_at = NOW()
    ";

    if (microfin_has_client_column($conn, 'verification_status')) {
        $clientUpdateSql .= ",
            verification_status = 'Pending'
        ";
    }

    $clientUpdateSql .= "
        WHERE client_id = ?
          AND tenant_id = ?
        LIMIT 1
    ";

    $clientUpdateStmt = $conn->prepare($clientUpdateSql);
    if (!$clientUpdateStmt) {
        throw new RuntimeException('Failed to prepare client update.');
    }

    if (microfin_has_client_column($conn, 'verification_status')) {
        $clientUpdateStmt->bind_param(
            'ssssssssssssssssissssdsssdssis',
            $firstName,
            $middleName,
            $lastName,
            $birthDate,
            $gender,
            $civilStatus,
            $phoneNumber,
            $emailAddress,
            $houseNo,
            $street,
            $barangay,
            $city,
            $province,
            $postal,
            $permHouseNo,
            $permStreet,
            $permBarangay,
            $permCity,
            $permProvince,
            $permPostal,
            $sameAsPermanent,
            $employmentStatus,
            $occupation,
            $employerName,
            $employerContact,
            $monthlyIncome,
            $comakerName,
            $comakerRelationship,
            $comakerContact,
            $comakerIncome,
            $comakerAddress,
            $idType,
            $client['client_id'],
            $tenantId
        );
    } else {
        $clientUpdateStmt->bind_param(
            'ssssssssssssssssissssdsssdssis',
            $firstName,
            $middleName,
            $lastName,
            $birthDate,
            $gender,
            $civilStatus,
            $phoneNumber,
            $emailAddress,
            $houseNo,
            $street,
            $barangay,
            $city,
            $province,
            $postal,
            $permHouseNo,
            $permStreet,
            $permBarangay,
            $permCity,
            $permProvince,
            $permPostal,
            $sameAsPermanent,
            $employmentStatus,
            $occupation,
            $employerName,
            $employerContact,
            $monthlyIncome,
            $comakerName,
            $comakerRelationship,
            $comakerContact,
            $comakerIncome,
            $comakerAddress,
            $idType,
            $client['client_id'],
            $tenantId
        );
    }

    $clientUpdateStmt->execute();
    $clientUpdateStmt->close();

    foreach ($documents as $document) {
        if (!is_array($document)) {
            continue;
        }

        $documentTypeId = (int) ($document['document_type_id'] ?? 0);
        $filePath = microfin_clean_string($document['file_path'] ?? '');
        $fileName = microfin_clean_string($document['file_name'] ?? '');

        if ($documentTypeId <= 0 || $filePath === '') {
            continue;
        }

        $docTypeStmt->bind_param('i', $documentTypeId);
        $docTypeStmt->execute();
        $docTypeExists = $docTypeStmt->get_result()->num_rows === 1;

        if (!$docTypeExists) {
            throw new RuntimeException('One of the selected document types is invalid.');
        }

        $absoluteFilePath = dirname(__DIR__) . '/../' . ltrim($filePath, '/\\');
        $fileSize = is_file($absoluteFilePath) ? filesize($absoluteFilePath) : null;
        $fileType = is_file($absoluteFilePath) ? (mime_content_type($absoluteFilePath) ?: null) : null;
        $documentNumber = $documentTypeId === 21 ? $idNumber : null;
        $documentExpiry = $documentTypeId === 21 ? $expiryDate : null;
        $documentNote = microfin_build_document_note($data, $documentTypeId);
        $resolvedFileName = $fileName !== '' ? $fileName : basename($filePath);

        $existingDocStmt->bind_param('isi', $client['client_id'], $tenantId, $documentTypeId);
        $existingDocStmt->execute();
        $existingDoc = $existingDocStmt->get_result()->fetch_assoc();

        if ($existingDoc) {
            $existingDocumentId = (int) $existingDoc['client_document_id'];
            $updateDocStmt->bind_param(
                'sssisssi',
                $resolvedFileName,
                $filePath,
                $documentNumber,
                $fileSize,
                $fileType,
                $documentNote,
                $documentExpiry,
                $existingDocumentId
            );
            $updateDocStmt->execute();
        } else {
            $insertDocStmt->bind_param(
                'isisssisss',
                $client['client_id'],
                $tenantId,
                $documentTypeId,
                $resolvedFileName,
                $filePath,
                $documentNumber,
                $fileSize,
                $fileType,
                $documentNote,
                $documentExpiry
            );
            $insertDocStmt->execute();
        }
    }

    $docTypeStmt->close();
    $existingDocStmt->close();
    $insertDocStmt->close();
    $updateDocStmt->close();

    $conn->commit();

    microfin_json_response([
        'success' => true,
        'message' => 'Verification profile submitted successfully.',
        'document_verification_status' => 'Pending',
        'client_id' => (int) $client['client_id'],
    ]);
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
    }

    microfin_json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
