"""Validate actual browser outputs, never manufacture missing evidence."""
import json
import os
from pathlib import Path
import re
import math

root = Path("browser-captures")
source = os.environ["F07_SOURCE_SHA"]
checkout = os.environ["GITHUB_SHA"]
for sha in (source, checkout):
    if not re.fullmatch(r"[a-f0-9]{40}", sha):
        raise ValueError("invalid source identity")

def require(condition, message):
    if not condition:
        raise RuntimeError(message)

def read(name):
    path = root / name
    require(path.is_file() and 0 < path.stat().st_size < 65536, name)
    return json.loads(path.read_text())

def metric(value, lower, upper):
    return type(value) in (int, float) and math.isfinite(value) and lower <= value < upper

results = []
for pack in ("medieval", "industrial", "guild"):
    campaign = read(f"f04-{pack}-campaign.json")
    experience = read(f"f05-{pack}-experience.json")
    legacy = read(f"g25-{pack}-metrics.json")
    for report in (campaign, experience, legacy):
        require(report["pack"] == pack, "incorrect pack")
    require(campaign["world"] == f"lab-{pack}-f04", "incorrect world")
    require(campaign["victory"] is True and campaign["reconnected"] is True
            and campaign["research"] == 2 and campaign["expeditions"] == 2, "incomplete campaign")
    for key in ("independentActors", "tradeCommitted", "sameIdAfterLostReply", "allianceAccepted",
                "messageEscaped", "blockAndUnblock", "operatorModeration", "reconnected", "keyboard"):
        require(experience[key] is True, f"{pack}: {key}")
    require(legacy["revokedSessionRejected"] is True and legacy["independentAccount"] is True,
            "legacy account isolation")
    measurements = experience["measurements"]
    require(len(measurements) == 2 and {m["device"] for m in measurements} == {"desktop", "mobile"},
            "two distinct viewports required")
    for m in measurements:
        require((m["width"], m["height"]) == ((1440, 900) if m["device"] == "desktop" else (390, 844)),
                "incorrect viewport")
        require(m["overflow"] is False and m["badBadges"] == 0
                and metric(m["nodes"], 1, 10000) and metric(m["frameP95Ms"], 0, 250)
                and metric(m["navMs"], 0, 30000) and metric(m["minNavHeight"], 44, 10000)
                and metric(m["minNavContrast"], 4.5, 21.01), "visual regression budget")
        for kind in ("campaign", "district"):
            path = root / f"f05-{pack}-{kind}-{m['device']}.png"
            require(path.is_file() and path.stat().st_size > 1000, "missing screenshot")
            with path.open("rb") as stream:
                require(stream.read(8) == b"\x89PNG\r\n\x1a\n", "invalid PNG")
    results.append({"pack": pack, "campaign": campaign, "experience": experience})
report = {"status": "passed", "sourceHead": source, "checkout": checkout,
          "scope": "isolated Chromium automated acceptance", "games": results}
(root / "f07-browser-evidence.json").write_text(json.dumps(report, indent=2, sort_keys=True) + "\n")
print("F07 browser evidence: three campaigns, three multiplayer experiences, six measured viewports")
