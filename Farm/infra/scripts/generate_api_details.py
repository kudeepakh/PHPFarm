import json
import datetime

spec_path = r"C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\docs\architecture\openapi.json"
out_path = r"C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm\docs\architecture\API_DETAILS.md"

with open(spec_path, "r", encoding="utf-8-sig") as f:
    spec = json.load(f)

global_security = spec.get("security")

lines = []
lines.append("# API Details")
lines.append(f"Generated: {datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
lines.append("")
lines.append("## Required Headers")
lines.append("- X-Correlation-Id (ULID, required)")
lines.append("- X-Transaction-Id (ULID, required)")
lines.append("- X-Request-Id (ULID, required)")
lines.append("- Accept: application/json")
lines.append("- Authorization: Bearer <token> (required for protected endpoints)")
lines.append("")
lines.append("## Standard Response Envelope")
lines.append("- success: boolean")
lines.append("- message: string")
lines.append("- data: object|array|null")
lines.append("- meta: object (timestamp, api_version, locale, pagination if applicable)")
lines.append("- trace: object (correlation_id, transaction_id, request_id)")
lines.append("")

paths = spec.get("paths", {})
for path in sorted(paths.keys()):
    methods = paths[path]
    for method in sorted(methods.keys()):
        if method.lower() not in {"get", "post", "put", "patch", "delete"}:
            continue
        op = methods[method]
        m = method.upper()
        lines.append(f"## {m} {path}")
        if op.get("summary"):
            lines.append(f"**Summary:** {op['summary']}")
        if op.get("description"):
            lines.append(f"**Description:** {op['description']}")
        if op.get("tags"):
            lines.append("**Tags:** " + ", ".join(op["tags"]))

        security = op.get("security") or global_security
        lines.append("**Auth:** " + ("Required" if security else "None"))
        lines.append("")

        params = op.get("parameters") or []
        if params:
            lines.append("**Parameters:**")
            for p in params:
                required = "required" if p.get("required") else "optional"
                schema = p.get("schema") or {}
                schema_type = schema.get("type", "object")
                lines.append(f"- {p.get('in')} {p.get('name')} ({schema_type}, {required})")
            lines.append("")

        req_body = op.get("requestBody")
        if req_body:
            lines.append("**Request Body:**")
            content = req_body.get("content", {})
            for ctype, cval in content.items():
                schema = cval.get("schema", {})
                schema_ref = schema.get("$ref")
                schema_type = schema.get("type")
                if schema_ref:
                    lines.append(f"- {ctype}: {schema_ref}")
                elif schema_type:
                    lines.append(f"- {ctype}: {schema_type}")
                else:
                    lines.append(f"- {ctype}: schema")
            lines.append("")

        responses = op.get("responses") or {}
        if responses:
            lines.append("**Responses:**")
            for code in sorted(responses.keys()):
                resp = responses[code]
                lines.append(f"- {code}: {resp.get('description', '')}")
                content = resp.get("content") or {}
                for ctype, cval in content.items():
                    schema = cval.get("schema", {})
                    schema_ref = schema.get("$ref")
                    schema_type = schema.get("type")
                    if schema_ref:
                        lines.append(f"  - {ctype}: {schema_ref}")
                    elif schema_type:
                        lines.append(f"  - {ctype}: {schema_type}")
                    else:
                        lines.append(f"  - {ctype}: schema")
            lines.append("")

        lines.append("---")
        lines.append("")

with open(out_path, "w", encoding="utf-8") as f:
    f.write("\n".join(lines))

print(f"API details written to {out_path}")
