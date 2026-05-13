import sys
from pypdf import PdfReader

try:
    reader = PdfReader(sys.argv[1])
    print(f"Total pages: {len(reader.pages)}")
    for i, page in enumerate(reader.pages):
        text = page.extract_text()
        print(f"Page {i+1} text length: {len(text)}")
        print(f"Page {i+1} text start: {text[:50].replace(chr(10), ' ')}")
except Exception as e:
    print(f"Error: {e}")
