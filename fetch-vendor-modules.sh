#!/bin/bash

if [ $# -ne 1 ]; then
    echo "Usage: $0 <connections-file>" >&2
    exit 1
fi

CONNECTIONS_FILE="$1"

if [ ! -f "$CONNECTIONS_FILE" ]; then
    echo "Error: File '$CONNECTIONS_FILE' not found" >&2
    exit 1
fi

echo "instance,composer_name,name,version"

while IFS= read -r connection; do
    [[ -z "$connection" || "$connection" =~ ^# ]] && continue

    username="${connection%@*}"
    lockfile="./${connection}-composer.lock"

    scp "${connection}:htdocs/composer.lock" "$lockfile" 2>/dev/null

    if [ ! -f "$lockfile" ]; then
        echo "Warning: Failed to retrieve composer.lock from $connection" >&2
        continue
    fi

    VENDORS="amasty|aheadworks|bsscommerce|mageworx|mageplaza|weltpixel|mageme|xtento|sixtomartin|ecomplugins"

    jq -r --arg username "$username" --arg vendors "$VENDORS" '
        ($vendors | split("|")) as $vendor_list |

        # All packages from target vendors
        [.packages[] | select(.name | test("^(" + $vendors + ")/"))] as $vendor_packages |

        # Collect packages required by another package from the same vendor
        [
            $vendor_packages[] |
            (.name | split("/")[0]) as $vendor |
            (.require // {} | keys[]) |
            select(startswith($vendor + "/"))
        ] | unique as $internal_deps |

        # Output only packages not in the internal deps list
        $vendor_packages[] |
        select(.name as $n | ($internal_deps | index($n)) | not) |
        [.name, $username, .description, .version] | @csv
    ' "$lockfile"

done < "$CONNECTIONS_FILE"
