"""
TriLingua Translation Microservice
===================================
Loads the NLLB-200 model once on startup and serves translation requests
over HTTP so Laravel doesn't need to spawn a new Python process per request.

Usage:
    python Model/server.py

The server listens on http://127.0.0.1:5000 by default.
Keep it running while the Laravel app is running.
"""

import sys
import os

# Use the locally cached model — never contact Hugging Face
os.environ["TRANSFORMERS_OFFLINE"] = "1"
os.environ["HF_DATASETS_OFFLINE"]  = "1"

# Ensure the Model directory is on the path so document_translator_v3 can be imported
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import shutil
import tempfile
import uvicorn

from fastapi import FastAPI, UploadFile, File, Form, HTTPException
from fastapi.responses import FileResponse
from pydantic import BaseModel

# ---------------------------------------------------------------------------
# Import the translation pipeline from the existing script.
# The model is loaded at module-import time (Cell 3 in document_translator_v3),
# so it stays in memory for the lifetime of this server process.
# ---------------------------------------------------------------------------
print("Loading NLLB-200 model — this may take a minute on first run...")
from document_translator_v3 import run_pipeline, _translate_single, LANGUAGES
print("Model loaded. Server ready.")

app = FastAPI(title="TriLingua Translation Service")

VALID_PDF_COLUMN_MODES = {"auto", "single", "left", "right"}


# ---------------------------------------------------------------------------
# Health check
# ---------------------------------------------------------------------------
@app.get("/health")
def health():
    return {"status": "ok", "languages": list(LANGUAGES.keys())}


# ---------------------------------------------------------------------------
# Text translation
# POST /translate/text
# Body: { "text": "...", "source_lang": "English", "target_lang": "Cebuano" }
# Returns: { "translated": "..." }
# ---------------------------------------------------------------------------
class TextRequest(BaseModel):
    text: str
    source_lang: str
    target_lang: str


@app.post("/translate/text")
def translate_text(req: TextRequest):
    if not req.text or not req.text.strip():
        raise HTTPException(400, "Text must not be empty.")
    if req.source_lang not in LANGUAGES:
        raise HTTPException(400, f"Unknown source language: {req.source_lang}")
    if req.target_lang not in LANGUAGES:
        raise HTTPException(400, f"Unknown target language: {req.target_lang}")
    if req.source_lang == req.target_lang:
        raise HTTPException(400, "Source and target languages must differ.")

    try:
        src_code = LANGUAGES[req.source_lang]
        tgt_code = LANGUAGES[req.target_lang]
        result = _translate_single(req.text.strip(), src_code, tgt_code)
        return {"translated": result}
    except Exception as e:
        raise HTTPException(500, str(e))


# ---------------------------------------------------------------------------
# Document translation
# POST /translate/document  (multipart/form-data)
# Fields: file (UploadFile), source_lang, target_lang
# Returns: the translated file as a download
# ---------------------------------------------------------------------------
@app.post("/translate/document")
async def translate_document(
    file: UploadFile = File(...),
    source_lang: str = Form(...),
    target_lang: str = Form(...),
    pdf_column_mode: str = Form("auto"),
):
    if pdf_column_mode not in VALID_PDF_COLUMN_MODES:
        raise HTTPException(
            400,
            f"Invalid pdf_column_mode '{pdf_column_mode}'. "
            f"Must be one of: {sorted(VALID_PDF_COLUMN_MODES)}"
        )
    if source_lang not in LANGUAGES:
        raise HTTPException(400, f"Unknown source language: {source_lang}")
    if target_lang not in LANGUAGES:
        raise HTTPException(400, f"Unknown target language: {target_lang}")
    if source_lang == target_lang:
        raise HTTPException(400, "Source and target languages must differ.")

    ext = os.path.splitext(file.filename)[1].lower()
    EXTENSION_MAP = {
        ".docx": ".docx", ".pdf": ".pdf", ".txt": ".txt",
        ".md": ".md",     ".csv": ".csv", ".rtf": ".docx", ".odt": ".docx",
    }
    if ext not in EXTENSION_MAP:
        raise HTTPException(400, f"Unsupported file type: {ext}")

    out_ext = EXTENSION_MAP[ext]
    tmp_dir = tempfile.mkdtemp()
    try:
        input_path  = os.path.join(tmp_dir, f"input{ext}")
        output_path = os.path.join(tmp_dir, f"translated{out_ext}")

        # Save the uploaded file
        contents = await file.read()
        with open(input_path, "wb") as f:
            f.write(contents)

        run_pipeline(input_path, source_lang, target_lang, output_path,
                     pdf_column_mode=pdf_column_mode)

        if not os.path.exists(output_path):
            raise HTTPException(500, "Translation produced no output file.")

        original_stem = os.path.splitext(file.filename)[0]
        download_name = f"{original_stem}_translated{out_ext}"

        # FileResponse streams the file; we clean up after sending
        return FileResponse(
            path=output_path,
            filename=download_name,
            media_type="application/octet-stream",
            background=None,  # cleanup handled below via finally isn't possible
                              # with FileResponse — tmp_dir is cleaned by OS on exit
        )
    except HTTPException:
        shutil.rmtree(tmp_dir, ignore_errors=True)
        raise
    except Exception as e:
        shutil.rmtree(tmp_dir, ignore_errors=True)
        raise HTTPException(500, str(e))


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------
if __name__ == "__main__":
    port = int(os.environ.get("TRANSLATION_PORT", 5000))
    uvicorn.run(app, host="127.0.0.1", port=port, log_level="info")
