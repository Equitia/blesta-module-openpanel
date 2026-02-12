# OpenPanel Module for Blesta

Provisions and manages OpenPanel accounts via the OpenAdmin API. This module allows you to resell OpenPanel hosting accounts, manage user packages, and provide single-sign-on (SSO) access directly from Blesta.

## Features

*   **Account Provisioning**: Automatically create new OpenPanel user accounts upon payment.
*   **Plan Management**: Upgrade or downgrade hosting packages seamlessly.
*   **Service Management**: Suspend, Unsuspend, and Terminate services from the Blesta admin area.
*   **Single Sign-On (SSO)**: Clients can log in to their OpenPanel account with one click from the Blesta client area.
*   **Live Plan Sync**: Automatically fetches available hosting plans from your OpenPanel server configuration.

## Requirements

*   Blesta 5.x
*   OpenPanel (Enterprise Edition recommended for API access)
*   PHP 7.4 or higher
*   cURL PHP Extension enabled

## Installation

1.  Upload the `openpanel` directory to your Blesta installation at `components/modules/openpanel/`.
2.  Log in to your Blesta Admin area.
3.  Navigate to **Settings > Modules > Available**.
4.  Find **OpenPanel** in the list and click **Install**.

## Configuration

### 1. Enable API Access on OpenPanel
Before adding the server to Blesta, ensure the OpenPanel API is enabled:
1.  Log in to your OpenAdmin panel.
2.  Go to **Settings > OpenPanel API**.
3.  Enable **API Access**.
4.  (Optional but recommended) Whitelist your Blesta server's IP address in the Firewall settings.

### 2. Add Server in Blesta
1.  Navigate to **Settings > Modules > Installed > OpenPanel > Add Server**.
2.  Fill in the server details:
    *   **Label**: A friendly name for this server (e.g., "OpenPanel US-East").
    *   **Hostname**: The hostname or IP address of your OpenPanel server (e.g., `srv1.example.com`).
    *   **API Port**: Default is `2087`.
    *   **Use HTTPS**: Check this box (highly recommended).
    *   **Verify SSL**: Check this box if you have a valid SSL certificate on your hostname.
    *   **Admin Username**: Your OpenAdmin username (usually `openpanel` or `admin`).
    *   **Admin Password**: Your OpenAdmin password.
3.  Click **Add Server**.

### 3. Create a Package
1.  Navigate to **Packages > Browse Packages > Create Package**.
2.  Set the basic details (Name, Pricing, etc.).
3.  In the **Module Options** section, select **OpenPanel**.
4.  **Server Group**: Select the server group you created.
5.  **OpenPanel Plan**: Select the hosting plan from the dropdown list.
    *   *Note: If the dropdown says "No plans found", verify your API credentials and ensure the API is enabled on the server.*
6.  Click **Create Package**.

## Troubleshooting

### "No plans found" when creating a package
*   **Cause**: Blesta cannot connect to the OpenPanel API or the API returned an error.
*   **Solution**:
    1.  Check the "OpenPanel Plan" dropdown; it will display the specific error message (e.g., `Error: 401 Unauthorized`).
    2.  Ensure port `2087` is open on the server firewall.
    3.  Verify your Admin Username and Password in the module settings.
    4.  Ensure the API is enabled in OpenAdmin settings.

### "Service creation failed"
*   Check the Module Logs in Blesta (**Tools > Logs > Module Logs**) for detailed error responses from the OpenPanel API.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Credits

*   **Author**: [Equitia](https://equitia.net)
*   **Original API**: OpenPanel Team
