"""Populate the Docket Accounts Tracker template with live data.

Usage: populate_tracker.py <template.xlsx> <data.json> <output.xlsx>

The template preserves every formula, named table, style, and title row
from the original spreadsheet — this script only ever writes into the
data rows of each table and expands table refs to match. Load-bearing
formulas (Summary's SUMIFS-by-year, the category rollup boxes on each
ledger sheet, PayPal running balances) are never touched directly; they
recalculate in Excel from the table data once opened.

Every row we write also gets its cell styles copied explicitly from a
known-good template row (see apply_row_style). Relying on formatting
that happens to already exist further down an unused part of the sheet
is fragile — it only reaches as far as the original file's data did
(e.g. row 189 on Expense), so once data grows past that point, or on
sheets like Summary that only ever had a few sample rows, new rows
silently fall back to Excel's default "General" format. Copying styles
on every write makes formatting survive no matter how much data grows.
"""
import json
import sys
from copy import copy
from datetime import date

import openpyxl
from openpyxl.utils import get_column_letter
from openpyxl.utils.cell import range_boundaries

TEMPLATE, DATA_JSON, OUTPUT = sys.argv[1], sys.argv[2], sys.argv[3]

with open(DATA_JSON) as f:
    data = json.load(f)

wb = openpyxl.load_workbook(TEMPLATE, data_only=False)


def parse_date(iso_string):
    """'2026-06-01' -> date(2026, 6, 1), so Excel stores a real date
    serial (matching the template's dd mmmm yyyy / m/d/yyyy formats)
    rather than a text string."""
    return date.fromisoformat(iso_string)


def write_text(ws, row, col, value):
    """openpyxl stores any string starting with '=' as a live formula.
    Free-text fields (counterparty, description, notes — some pre-filled
    by OCR from receipts we didn't author) must never be able to smuggle
    a formula into the workbook, so force plain-string storage."""
    cell = ws.cell(row=row, column=col, value=value)
    if isinstance(value, str) and value.startswith('='):
        cell.data_type = 's'
    return cell


def apply_row_style(ws, style_row, target_row, min_col, max_col):
    """Copy every cell's font/border/fill/number_format/alignment from
    style_row to target_row, column by column."""
    if style_row == target_row:
        return
    for col in range(min_col, max_col + 1):
        src = ws.cell(row=style_row, column=col)
        dst = ws.cell(row=target_row, column=col)
        dst.font = copy(src.font)
        dst.border = copy(src.border)
        dst.fill = copy(src.fill)
        dst.number_format = src.number_format
        dst.alignment = copy(src.alignment)
        dst.protection = copy(src.protection)


def populate_ledger_table(sheet_name, table_name, rows):
    """Write rows into a ledger table (Income/Expenses/CF), columns B..M:
    Date, Category(blank), Sub-Category, Counterparty, Currency, FX,
    Amount, VAT, Total(blank — unused in the original), Amount GBP,
    Description, Payment Method. Expands the table ref to fit.
    """
    ws = wb[sheet_name]
    tbl = ws.tables[table_name]
    min_col, min_row, max_col, max_row = range_boundaries(tbl.ref)
    header_row = min_row
    style_row = header_row + 1

    row_num = header_row + 1
    for r in rows:
        apply_row_style(ws, style_row, row_num, min_col, max_col)
        ws.cell(row=row_num, column=2, value=parse_date(r['date']))  # B Date
        ws.cell(row=row_num, column=3, value=None)                   # C Category (always blank in original)
        write_text(ws, row_num, 4, r['category'])                    # D Sub-Category
        write_text(ws, row_num, 5, r['counterparty'])                # E Customer/Vendor
        ws.cell(row=row_num, column=6, value=r['currency'])          # F Currency
        ws.cell(row=row_num, column=7, value=r['fx_rate'])           # G FX (to GBP)
        ws.cell(row=row_num, column=8, value=r['amount'])            # H Amount
        ws.cell(row=row_num, column=9, value=r['vat'])               # I VAT
        # J Total amount: left blank — the original never populates this
        # column either (net/VAT/GBP carry the real numbers).
        ws.cell(row=row_num, column=11, value=r['amount_gbp'])       # K Amount (£)
        write_text(ws, row_num, 12, r['description'])                # L Description
        write_text(ws, row_num, 13, r['payment_method'])             # M Payment Method
        row_num += 1

    last_row = max(row_num - 1, header_row + 1)
    tbl.ref = f'{get_column_letter(min_col)}{header_row}:{get_column_letter(max_col)}{last_row}'


populate_ledger_table('Income', 'Income_Table', data['income'])
populate_ledger_table('Expense', 'Expenses_Table', data['expense'])
populate_ledger_table('CF', 'CF_Table', data['cash_flow'])


def populate_summary(years):
    """Extend Summary_Table with one row per year present in the data.

    The template's first data row uses a cumulative "<=" formula (the
    earliest year rolls up all prior history — matches the original's
    "earliest year includes prior history" convention) while every
    row after it uses a ">=Jan1 AND <=Dec31" per-year range. Both
    patterns are read from the template before it's overwritten, then
    applied: first entry in `years` gets the cumulative formula, every
    later entry gets the range formula. Copying only the first row's
    formula into every row (an earlier bug here) silently turned every
    year into a running cumulative total instead of an annual one.

    Cell styling is copied from the template's first data row onto
    every row, same reasoning as apply_row_style above — otherwise the
    template's original 3 sample rows (2024-2026) format correctly but
    every extra year we add falls back to unformatted "General" numbers.
    """
    ws = wb['Summary']
    tbl = ws.tables['Summary_Table']
    min_col, min_row, max_col, max_row = range_boundaries(tbl.ref)
    header_row = min_row
    style_row = header_row + 1

    cumulative_formulas = {
        col: ws.cell(row=header_row + 1, column=col).value
        for col in range(min_col + 1, max_col + 1)
    }
    range_formulas = {
        col: ws.cell(row=header_row + 2, column=col).value
        for col in range(min_col + 1, max_col + 1)
    }

    row_num = header_row + 1
    for i, year in enumerate(years):
        apply_row_style(ws, style_row, row_num, min_col, max_col)
        ws.cell(row=row_num, column=min_col, value=year)
        formulas = cumulative_formulas if i == 0 else range_formulas
        for col, formula in formulas.items():
            ws.cell(row=row_num, column=col, value=formula)
        row_num += 1

    last_row = max(row_num - 1, header_row + 1)
    tbl.ref = f'{get_column_letter(min_col)}{header_row}:{get_column_letter(max_col)}{last_row}'


populate_summary(data['years'])


def populate_paypal(table_name, header_row, rows):
    ws = wb['PayPal']
    tbl = ws.tables[table_name]
    min_col, min_row, max_col, max_row = range_boundaries(tbl.ref)
    style_row = header_row + 1

    row_num = header_row + 1
    prev_row = None
    for r in rows:
        apply_row_style(ws, style_row, row_num, min_col, max_col)
        ws.cell(row=row_num, column=2, value=parse_date(r['date']))  # B Date
        write_text(ws, row_num, 3, r['description'])             # C Description
        write_text(ws, row_num, 4, r['source'])                  # D Source
        ws.cell(row=row_num, column=5, value=r['amount'])        # E Amount
        if prev_row is None:
            ws.cell(row=row_num, column=6, value=r['amount'])    # F Balance (first row: literal)
        else:
            ws.cell(row=row_num, column=6, value=f'=F{prev_row}+E{row_num}')
        prev_row = row_num
        row_num += 1

    last_row = max(row_num - 1, header_row + 1)
    tbl.ref = f'{get_column_letter(min_col)}{header_row}:{get_column_letter(max_col)}{last_row}'


populate_paypal('PayPal_USD', 6, data['paypal_usd'])
populate_paypal('PayPal_EUR', 27, data['paypal_eur'])


#: Number formats lifted directly from the original workbook's Balances
#: sheet. Not discoverable from the template at population time — the
#: per-row currency values there were deliberately cleared when the
#: template was built (build_template.py), so detecting "which row had
#: which currency's format" is unreliable once rows get reordered
#: (an earlier version of this function did exactly that and produced
#: mismatched formats after the account sort). Hardcoding is simpler
#: and correct as long as the app only ever uses these three currencies.
BALANCE_CURRENCY_FORMATS = {
    'GBP': '\\£#,##0.00',
    'USD': '\\$#,##0.00',
    'EUR': '[$€-407]#,##0.00',
}


def populate_balances(rows, date_columns):
    """rows: list of {account, currency, balances: {date: amount}, notes}
    date_columns: list of {col_letter, date} matching the template's
    fixed header columns (D..G: 31 Dec 2023 / 2024 / 2025 / current).
    """
    ws = wb['Balances']
    generic_style_row = 7  # Account/Currency/Notes columns: plain text, same style everywhere

    row_num = 7
    for r in rows:
        apply_row_style(ws, generic_style_row, row_num, 2, 8)

        number_format = BALANCE_CURRENCY_FORMATS.get(r['currency'], BALANCE_CURRENCY_FORMATS['GBP'])
        for dc in date_columns:
            ws[f'{dc["col_letter"]}{row_num}'].number_format = number_format

        write_text(ws, row_num, 2, r['account'])
        ws.cell(row=row_num, column=3, value=r['currency'])
        for dc in date_columns:
            value = r['balances'].get(dc['date'])
            if value is not None:
                ws[f'{dc["col_letter"]}{row_num}'] = value
        write_text(ws, row_num, 8, r.get('notes') or None)
        row_num += 1


populate_balances(data['balances'], data['balance_date_columns'])

wb.save(OUTPUT)
print(f'Saved {OUTPUT}')
