import urllib.parse
import base64
import zlib
import json
import xml.dom.minidom

def pretty_format(data: str) -> str:
    # Thử parse JSON
    try:
        parsed_json = json.loads(data)
        return json.dumps(parsed_json, indent=4, ensure_ascii=False)
    except Exception:
        pass

    # Thử parse XML
    try:
        parsed_xml = xml.dom.minidom.parseString(data)
        return parsed_xml.toprettyxml(indent="  ")
    except Exception:
        pass

    # Trường hợp khác: chỉ strip rác
    return data.strip()

def decode_xxe_payload(encoded_payload: str):
    if encoded_payload.startswith("data:,"):
        encoded_payload = encoded_payload[6:]

    url_decoded = urllib.parse.unquote(encoded_payload)

    try:
        base64_decoded = base64.b64decode(url_decoded)
    except Exception as e:
        print("[!] Base64 decode error:", e)
        return

    try:
        decompressed = zlib.decompress(base64_decoded, -15)
        decoded_text = decompressed.decode(errors="replace")
        formatted = pretty_format(decoded_text)

        print("✅ Decoded & formatted content:\n")
        print(formatted)
    except Exception as e:
        print("[!] Zlib decompress error:", e)

if __name__ == "__main__":
    encoded_input = input("Paste XXE encoded data (starting with or without 'data:,'): ").strip()
    decode_xxe_payload(encoded_input)
