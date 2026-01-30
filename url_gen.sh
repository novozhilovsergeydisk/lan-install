#!/bin/bash
set -e

if [ -z "$1" ]; then
  echo "Использование: ./url_gen.sh <ID_АДРЕСА>"
  exit 1
fi

ADDRESS_ID=$1

php artisan tinker --execute="echo route('reports.address-history.public', ['addressId' => $ADDRESS_ID, 'token' => md5('$ADDRESS_ID' . config('app.key') . 'address-history')]);" | sed 's|http://localhost|http://localhost:8000|'