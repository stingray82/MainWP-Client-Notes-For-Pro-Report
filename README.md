MainWP-Client-Notes-For-Pro-Report
==================================

![MainWP Work Notes](https://github.com/stingray82/repo-images/raw/main/Mainwp-work-notes-pro-report/Extention.png)

>   **Note:** This extension requires **MainWP Pro Reports**.

What’s It Do?
-------------

This plugin allows you to add work notes and client notes on a **per-site
basis**, filtered by date range, and automatically included in your **MainWP Pro
Reports** using a special token.

Usage Instructions
------------------

1.  **Install the Extension**  
    

    ![Installed](https://github.com/stingray82/repo-images/raw/main/Mainwp-work-notes-pro-report/Installed.png)

2.  **Access "Work Notes" from a Child Site Menu**  
    

    ![Menu Item](https://github.com/stingray82/repo-images/raw/main/Mainwp-work-notes-pro-report/Additional_Menu_Item.png)

3.  **Open the Work Notes Page**  
    

    ![Work Notes Page](https://github.com/stingray82/repo-images/raw/main/Mainwp-work-notes-pro-report/Screen.png)

4.  **Add Your Notes and Use the Token**  
    Use `[client.customwork.notes]` in your Pro Report template to include these
    notes in client-facing reports.  
    

    ![Example Code](https://github.com/stingray82/repo-images/raw/main/Mainwp-work-notes-pro-report/Example_code_in_use.png)

5.  **Your Notes Render in the Final Report**  
    

    ![Rendered Code](https://github.com/stingray82/repo-images/raw/main/Mainwp-work-notes-pro-report/Rendered_Code.png)

Coming in v1.3.0
----------------

-   Notes are now saved to a **dedicated database table**
    (`wp_mainwp_work_notes`) for better scalability and performance.

-   **Flatpickr** date selector with localized display format.

-   Automatically populates today's date when creating a new note.

-   Dynamic "Save Note" / "Update Note" button based on context.

-   Seamless **automatic migration** of existing notes from `wp_options`.

-   **Admin bar fallback** allows manual migration trigger if needed.

-   Future-proof cleanup logic to remove legacy data in v1.3.2+.

 
