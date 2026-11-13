import io
from pypdf import PdfReader
from pypdf.errors import PdfReadError

def extract_text(pdf_bytes: bytes) -> tuple[str, list[str]]:
    warnings = []
    
    # Kiểm tra Magic Header PDF
    if not pdf_bytes.startswith(b'%PDF-'):
        warnings.append("PDF_INVALID")
        return "", warnings

    try:
        pdf_file = io.BytesIO(pdf_bytes)
        reader = PdfReader(pdf_file)
        
        if reader.is_encrypted:
            warnings.append("PDF_ENCRYPTED")
            return "", warnings

        extracted_text = []
        for page in reader.pages:
            page_text = page.extract_text()
            if page_text:
                extracted_text.append(page_text)
                
        full_text = "\n".join(extracted_text).strip()
        
        if not full_text:
            warnings.append("PDF_NO_TEXT")
            return "", warnings
            
        return full_text, warnings
        
    except PdfReadError:
        warnings.append("PDF_INVALID")
        return "", warnings
    except Exception as e:
        warnings.append("PDF_INVALID")
        return "", warnings
