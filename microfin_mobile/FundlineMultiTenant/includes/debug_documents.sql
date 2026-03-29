-- Check what document types exist in the database
SELECT * FROM document_types ORDER BY document_type_id;

-- Check what documents are uploaded for the client
-- Replace CLIENT_ID with the actual client_id you're testing with
SELECT 
    cd.client_document_id,
    cd.client_id,
    dt.document_name,
    cd.file_name,
    cd.file_path,
    cd.upload_date
FROM client_documents cd
JOIN document_types dt ON cd.document_type_id = dt.document_type_id
WHERE cd.client_id = 1  -- Change this to your test client ID
ORDER BY cd.upload_date DESC;

-- Check if there are any documents without matching document types
SELECT 
    cd.*
FROM client_documents cd
LEFT JOIN document_types dt ON cd.document_type_id = dt.document_type_id
WHERE dt.document_type_id IS NULL;
