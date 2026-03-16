#!/usr/bin/env python3
"""
run_all.py — LANACC Master ETL runner
Usage:  python run_all.py [--only salaries] [--month 1] [--year 2026]

Options:
  --only   Run only one importer: salaries|bim|accounts|petty_cash|shareholders|bdo
  --month  Filter to a specific month number (optional)
  --year   Year (default 2026)
"""
import os

os.environ.setdefault('PYTHONIOENCODING','utf-8')
os.environ.setdefault('PYTHONUTF8','1')
import click
from rich.console import Console
from rich.panel import Panel
from datetime import datetime

console = Console()


@click.command()
@click.option("--only", default=None,
              help="Run only: salaries|bim|accounts|petty_cash|shareholders|bdo")
@click.option("--month", default=None, type=int, help="Month 1-12 (optional filter)")
@click.option("--year",  default=2026,  type=int, help="Year (default 2026)")
def main(only, month, year):
    start = datetime.now()
    console.print(Panel(
        f"[bold cyan]LANACC ETL Pipeline[/bold cyan]\n"
        f"Year: {year}  |  Month: {month or 'all'}  |  Mode: {only or 'all importers'}",
        title="[bold blue]Starting Import[/bold blue]"
    ))

    from importers import import_salaries, import_bim, import_accounts
    from importers import import_petty_cash, import_shareholders, import_bdo

    importers = {
        "salaries":     import_salaries.run,
        "bim":          import_bim.run,
        "accounts":     import_accounts.run,
        "petty_cash":   import_petty_cash.run,
        "shareholders": import_shareholders.run,
        "bdo":          import_bdo.run,
    }

    if only:
        if only not in importers:
            console.print(f"[red]Unknown importer: {only}. Choose from: {', '.join(importers)}[/red]")
            return
        importers[only]()
    else:
        for name, fn in importers.items():
            console.rule(f"[bold]{name}[/bold]")
            fn()

    elapsed = (datetime.now() - start).seconds
    console.print(Panel(
        f"[green]All done! Completed in {elapsed}s[/green]",
        title="[bold green]Import Complete[/bold green]"
    ))


if __name__ == "__main__":
    main()


