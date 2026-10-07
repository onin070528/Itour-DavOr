# iTOUR Development Rules

## LGU municipality authorization

An LGU user is permanently bound to exactly one municipality through the authenticated user's `municipality_id`. Every LGU query, policy, controller action, report, export, dashboard aggregate, AJAX endpoint, and direct-record access must derive municipality ownership server-side from the authenticated user and the related record.

Never trust municipality IDs, establishment IDs, route parameters, hidden form fields, query parameters, or client-side filtering supplied by the request for authorization. A cross-municipality access attempt must return 403 and be recorded in `security_logs` without logging passwords or other sensitive request data.

LGU users may only access and manage establishments, establishment accounts, arrivals, reports, images, and other LGU-managed records belonging to their assigned municipality. PTO users retain province-wide access across all municipalities.
