import os
import re
import datetime

ROOT = r"C:\Users\Deepak\OneDrive\Desktop\PHPFrarm\Farm"
APP_DIRS = [
    os.path.join(ROOT, "backend", "app"),
    os.path.join(ROOT, "backend", "modules"),
]
CONTROLLER_DIRS = [
    os.path.join(ROOT, "backend", "app", "Controllers"),
    os.path.join(ROOT, "backend", "modules", "Auth", "Controllers"),
    os.path.join(ROOT, "backend", "modules", "User", "Controllers"),
    os.path.join(ROOT, "backend", "modules", "Storage", "Controllers"),
]
OUT_PATH = os.path.join(ROOT, "docs", "architecture", "API_DETAILS.md")

route_group_re = re.compile(r"#\[RouteGroup\(([^\)]*)\)\]")
route_re = re.compile(r"#\[Route\(([^\)]*)\)\]")
router_group_re = re.compile(r"Router::group\('([^']+)'\s*,\s*\[([^\]]*)\]", re.IGNORECASE)
router_route_re = re.compile(r"Router::(get|post|put|patch|delete)\('([^']+)'\s*,\s*\[([^\]]+)\]", re.IGNORECASE)
function_re = re.compile(r"function\s+([a-zA-Z0-9_]+)\s*\(")
class_re = re.compile(r"class\s+([A-Za-z0-9_]+)")

string_re = re.compile(r"'([^']*)'")


def parse_route_args(arg_text: str):
    path = None
    method = None
    description = None
    middleware = []

    strings = string_re.findall(arg_text)
    if strings:
        path = strings[0]

    method_match = re.search(r"method\s*:\s*'([^']+)'", arg_text)
    if method_match:
        method = method_match.group(1).upper()

    desc_match = re.search(r"description\s*:\s*'([^']+)'", arg_text)
    if desc_match:
        description = desc_match.group(1)

    middleware_match = re.search(r"middleware\s*:\s*\[([^\]]*)\]", arg_text)
    if middleware_match:
        middleware = [m.strip().strip("'") for m in middleware_match.group(1).split(',') if m.strip()]

    return path, method, description, middleware


def collect_routes():
    routes = []
    files_with_routes = set()
    skipped_files = {
        os.path.join(ROOT, "backend", "app", "Console", "Commands", "MakeModuleCommand.php"),
    }

    for base in APP_DIRS:
        for root, _, files in os.walk(base):
            for file in files:
                if not file.endswith('.php'):
                    continue
                path = os.path.join(root, file)
                if path in skipped_files:
                    continue
                with open(path, 'r', encoding='utf-8', errors='ignore') as f:
                    lines = f.readlines()

                current_group = ''
                current_class = None
                in_block_comment = False

                for i, line in enumerate(lines):
                    stripped = line.lstrip()
                    if '/*' in stripped:
                        in_block_comment = True
                    if '*/' in stripped:
                        in_block_comment = False
                        continue

                    if in_block_comment or stripped.startswith('//') or stripped.startswith('*'):
                        continue

                    group_match = route_group_re.search(line)
                    if group_match:
                        group_args = group_match.group(1)
                        group_strings = string_re.findall(group_args)
                        if group_strings:
                            current_group = group_strings[0]

                    class_match = class_re.search(line)
                    if class_match:
                        current_class = class_match.group(1)

                    route_match = route_re.search(line)
                    if route_match:
                        args = route_match.group(1)
                        path_part, method, description, middleware = parse_route_args(args)
                        if not method:
                            method = 'GET'
                        function_name = None
                        for j in range(i + 1, min(i + 6, len(lines))):
                            func_match = function_re.search(lines[j])
                            if func_match:
                                function_name = func_match.group(1)
                                break

                        full_path = (current_group or '') + (path_part or '')
                        routes.append({
                            'method': method,
                            'path': full_path if full_path else (path_part or ''),
                            'description': description or '',
                            'controller': current_class or '',
                            'handler': function_name or '',
                            'source': path,
                            'middleware': middleware
                        })
                        files_with_routes.add(path)

    # Parse routes.php files using Router::group and Router::get/post...
    for base in APP_DIRS:
        for root, _, files in os.walk(base):
            for file in files:
                if file != 'routes.php':
                    continue
                path = os.path.join(root, file)
                with open(path, 'r', encoding='utf-8', errors='ignore') as f:
                    content = f.read()

                group_prefix = ''
                group_middleware = []
                group_match = router_group_re.search(content)
                if group_match:
                    group_prefix = group_match.group(1)
                    group_middleware = [m.strip().strip("'") for m in group_match.group(2).split(',') if m.strip()]

                for match in router_route_re.finditer(content):
                    method = match.group(1).upper()
                    rel_path = match.group(2)
                    handler = match.group(3).strip()
                    full_path = (group_prefix or '') + rel_path
                    routes.append({
                        'method': method,
                        'path': full_path,
                        'description': 'Router route',
                        'controller': handler,
                        'handler': '',
                        'source': path,
                        'middleware': group_middleware
                    })
                    files_with_routes.add(path)

    return routes, files_with_routes


def write_markdown(routes, files_with_routes):
    routes = sorted(routes, key=lambda r: (r['path'], r['method']))
    total_routes = len(routes)
    attr_routes = len([r for r in routes if r['source'].endswith('.php') and 'routes.php' not in r['source']])
    router_routes = len([r for r in routes if r['source'].endswith('routes.php')])

    controller_files = []
    for base in CONTROLLER_DIRS:
        if not os.path.isdir(base):
            continue
        for root, _, files in os.walk(base):
            for file in files:
                if file.endswith('.php'):
                    controller_files.append(os.path.join(root, file))

    controllers_without_routes = sorted([
        f for f in controller_files if f not in files_with_routes
    ])

    lines = []
    lines.append("# API Details (Parsed from Code)")
    lines.append(f"Generated: {datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    lines.append("")
    lines.append("## Coverage Summary")
    lines.append(f"- Total routes: {total_routes}")
    lines.append(f"- Attribute routes: {attr_routes}")
    lines.append(f"- routes.php routes: {router_routes}")
    if controllers_without_routes:
        lines.append(f"- Controllers without routes: {len(controllers_without_routes)}")
    else:
        lines.append("- Controllers without routes: 0")
    lines.append("")

    if controllers_without_routes:
        lines.append("## Potentially Missing Routes (Controllers without Route attributes)")
        for f in controllers_without_routes:
            lines.append(f"- {f}")
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

    current_path = None
    for r in routes:
        if r['path'] != current_path:
            current_path = r['path']
            lines.append(f"## {current_path}")

        lines.append(f"- **{r['method']}** — {r['description'] or 'No description'}")
        if r['controller'] or r['handler']:
            if r['handler']:
                lines.append(f"  - Handler: {r['controller']}::{r['handler']}")
            else:
                lines.append(f"  - Handler: {r['controller']}")
        if r.get('middleware'):
            lines.append(f"  - Middleware: {', '.join(r['middleware'])}")
        lines.append(f"  - Source: {r['source']}")
        lines.append("")

    with open(OUT_PATH, 'w', encoding='utf-8') as f:
        f.write('\n'.join(lines))


def main():
    routes, files_with_routes = collect_routes()
    write_markdown(routes, files_with_routes)


if __name__ == '__main__':
    main()
