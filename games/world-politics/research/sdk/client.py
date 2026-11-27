"""Minimal dependency-free client for the separate World Politics Research API."""
import json
import urllib.request
import urllib.parse


class ResearchClient:
    def __init__(self, base_url, token, timeout=20):
        parsed = urllib.parse.urlsplit(base_url)
        if parsed.scheme != "https" or not parsed.netloc or parsed.username or parsed.password or parsed.query or parsed.fragment:
            raise ValueError("HTTPS research endpoint required")
        if not isinstance(token, str) or len(token) != 64 or any(c not in "0123456789abcdef" for c in token):
            raise ValueError("Invalid research token")
        self.base = base_url.rstrip("/")
        self.token = token
        self.timeout = timeout

    def _request(self, method, path, payload=None, key=None):
        headers = {"Authorization": "Bearer " + self.token}
        body = None
        if payload is not None:
            headers["Content-Type"] = "application/json"
            body = json.dumps(payload).encode("utf-8")
        if key is not None:
            headers["Idempotency-Key"] = key
        req = urllib.request.Request(self.base + path, data=body, headers=headers, method=method)
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, req, fp, code, msg, hdrs, newurl):
                return None
        with urllib.request.build_opener(NoRedirect()).open(req, timeout=self.timeout) as response:
            data = response.read(524289)
            if len(data) > 524288:
                raise ValueError("Response exceeds client bound")
            return json.loads(data)

    @staticmethod
    def _id(job_id):
        if not isinstance(job_id, str) or len(job_id) != 32 or any(c not in "0123456789abcdef" for c in job_id):
            raise ValueError("Invalid job ID")
        return job_id

    def models(self):
        return self._request("GET", "/models")

    def submit(self, kind, parameters, idempotency_key):
        return self._request("POST", "/experiments", {"type": kind, "parameters": parameters}, idempotency_key)

    def read(self, job_id):
        return self._request("GET", "/experiments/" + self._id(job_id))

    def cancel(self, job_id):
        return self._request("DELETE", "/experiments/" + self._id(job_id))

    def csv(self, job_id):
        return self._request("GET", "/experiments/" + self._id(job_id) + "/csv")

    def usage(self):
        return self._request("GET", "/usage")

    def entitlements(self):
        return self._request("GET", "/entitlements")

    def dataset(self, scope="own", after=None, through=None):
        if scope not in ("own", "global"):
            raise ValueError("Invalid dataset scope")
        path = "/datasets/" + scope
        if after is not None or through is not None:
            if not isinstance(after, int) or not isinstance(through, int) or not 0 <= after <= through:
                raise ValueError("Invalid dataset cursor")
            path += f"/{after}/{through}"
        return self._request("GET", path)
