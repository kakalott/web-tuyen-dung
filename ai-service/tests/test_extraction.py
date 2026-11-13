from unittest.mock import MagicMock, patch
from app.schemas.extraction import SkillCatalogItem
from app.services.extraction_pipeline import extract


def _sample_catalog() -> list[SkillCatalogItem]:
    return [
        SkillCatalogItem(skill_id=1, canonical_name="PHP", aliases=[]),
        SkillCatalogItem(skill_id=2, canonical_name="JavaScript", aliases=["JS", "ECMAScript"]),
        SkillCatalogItem(skill_id=3, canonical_name="REST API", aliases=["RESTful API"]),
        SkillCatalogItem(skill_id=4, canonical_name="Java", aliases=[]),
    ]


def test_extract_text_and_alias():
    """Trích xuất text hợp lệ, nhận diện alias đúng biên từ (JS -> JavaScript, không nhầm Java)."""
    mock_page = MagicMock()
    mock_page.extract_text.return_value = "Lập trình viên PHP, JS, ECMAScript và RESTful API."

    mock_reader = MagicMock()
    mock_reader.is_encrypted = False
    mock_reader.pages = [mock_page]

    with patch("app.services.pdf_parser.PdfReader", return_value=mock_reader):
        result = extract(b"%PDF-1.4 fake-valid-bytes", _sample_catalog())

    assert result.parser_status == "success"
    assert result.warnings == []
    assert "lập trình viên" in result.normalized_text

    detected_map = {item.skill_id: item.matched_alias for item in result.detected_skills}
    assert set(detected_map.keys()) == {1, 2, 3}
    assert 4 not in detected_map  # "JavaScript" không được nhận nhầm thành "Java"


def test_no_text():
    """PDF scan chỉ có ảnh (không có text layer) phải trả PDF_NO_TEXT."""
    mock_page = MagicMock()
    mock_page.extract_text.return_value = "   \n  "

    mock_reader = MagicMock()
    mock_reader.is_encrypted = False
    mock_reader.pages = [mock_page]

    with patch("app.services.pdf_parser.PdfReader", return_value=mock_reader):
        result = extract(b"%PDF-1.4 image-only-bytes", _sample_catalog())

    assert result.parser_status == "failed"
    assert "PDF_NO_TEXT" in result.warnings


def test_invalid_pdf_magic_bytes():
    """File không có magic header %PDF- phải trả PDF_INVALID."""
    result = extract(b"NOT_A_PDF_FILE_CONTENT", _sample_catalog())
    assert result.parser_status == "failed"
    assert "PDF_INVALID" in result.warnings


def test_encrypted_pdf():
    """File PDF đặt mật khẩu phải trả PDF_ENCRYPTED."""
    mock_reader = MagicMock()
    mock_reader.is_encrypted = True

    with patch("app.services.pdf_parser.PdfReader", return_value=mock_reader):
        result = extract(b"%PDF-1.4 encrypted-bytes", _sample_catalog())

    assert result.parser_status == "failed"
    assert "PDF_ENCRYPTED" in result.warnings
