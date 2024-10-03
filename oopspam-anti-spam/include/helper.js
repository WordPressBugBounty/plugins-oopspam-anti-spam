jQuery(document).ready(function($) {


    document.querySelectorAll('.select').forEach((el)=>{
        let settings = {};
         new TomSelect(el, settings);
    });

    let adminEmailList = document.getElementById('admin-email-list');
    if (adminEmailList) {
         const settings = {create: true};
         adminEmailList = new TomSelect(adminEmailList, settings);
    }

    $("#empty-ham-entries").click(function (e) {
        let that = this;
        this.classList.add("disabled");
    
        // Prompt for confirmation
        const confirmed = confirm("Are you sure you want to empty ham entries? This action cannot be undone.");
    
        if (confirmed) {
            const data = {
                action: 'empty_ham_entries',
                action_type: 'empty-entries',
                nonce: customScript.emptyHamEntriesNonce,
            };
            jQuery.post(ajaxurl, data, function (response) {
                if (response["success"]) {
                    that.classList.remove("disabled");
                    location.reload();
                }
            });
        } else {
            // User cancelled action
            that.classList.remove("disabled");
        }
    });
    

    $("#empty-spam-entries").click(function (e) {
        let that = this;
        this.classList.add("disabled");
    
        // Prompt for confirmation
        const confirmed = confirm("Are you sure you want to empty spam entries? This action cannot be undone.");
    
        if (confirmed) {
            const data = {
                action: 'empty_spam_entries',
                action_type: 'empty-entries',
                nonce: customScript.emptySpamEntriesNonce
            };
            jQuery.post(ajaxurl, data, function (response) {
                if (response["success"]) {
                    that.classList.remove("disabled");
                    location.reload();
                }
            });
        } else {
            // User cancelled action
            that.classList.remove("disabled");
        }
    });
    

    $("#export-spam-entries").click(function (e) {
        let that = this;
        this.classList.add("disabled");
    
        const data = {
            action: 'export_spam_entries',
            action_type: 'export-entries',
            nonce: customScript.exportSpamEntriesNonce
        };
    
        // Create a hidden form to submit
        const form = document.createElement('form');
        form.style.display = 'none';
        form.method = 'POST';
        form.action = ajaxurl;
    
        for (const key in data) {
            if (data.hasOwnProperty(key)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = data[key];
                form.appendChild(input);
            }
        }
    
        document.body.appendChild(form);
    
        // Submit the form
        form.submit();
    
        // Clean up after submission
        form.remove();
    
        // Re-enable the button
        that.classList.remove("disabled");
    
        e.preventDefault();
    });
    
    $("#export-ham-entries").click(function (e) {
        let that = this;
        this.classList.add("disabled");
    
        const data = {
            action: 'export_ham_entries',
            action_type: 'export-entries',
            nonce: customScript.exportHamEntriesNonce
        };
    
        // Create a hidden form to submit
        const form = document.createElement('form');
        form.style.display = 'none';
        form.method = 'POST';
        form.action = ajaxurl;
    
        for (const key in data) {
            if (data.hasOwnProperty(key)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = data[key];
                form.appendChild(input);
            }
        }
    
        document.body.appendChild(form);
    
        // Submit the form
        form.submit();
    
        // Clean up after submission
        form.remove();
    
        // Re-enable the button
        that.classList.remove("disabled");
    
        e.preventDefault();
    });
    

    // Show an alert if the Sensitivity Level is different than 3
    const rangeInput = document.getElementById("oopspam_spam_score_threshold");
    let showAlert = true;

    if(rangeInput) {
        rangeInput.addEventListener("input", function() {
        if (rangeInput.value !== "3" && showAlert) {
            alert("It's highly recommended to use the 'Moderate' sensitivity level, which provides a great balance between catching spam and allowing legitimate messages through.");
            showAlert = false;
        }
        });
    }


    // Add select/deselect all toggle to optgroups in chosen
    $(document).on('click', '.group-result', function() {
        // Get unselected items in this group
        var unselected = $(this).nextUntil('.group-result').not('.result-selected');
        if ( unselected.length ) {
            // Select all items in this group
            unselected.trigger('mouseup');
        } else {
            $(this).nextUntil('.group-result').each(function() {
                // Deselect all items in this group
                $('a.search-choice-close[data-option-array-index="' + $(this).data('option-array-index') + '"]').trigger('click');
            });
        }
    });


    hideAllowedCountriesSection();
    $("#ip_check_support").click(function () {
        hideAllowedCountriesSection();
        hideBlockedCountriesSection();
    })

    function hideAllowedCountriesSection() {
        if ($("#ip_check_support").is(":checked")) {
            $("#allowcountry").closest("tr").hide();
        } else if($("#ip_check_support").is(":not(:checked)")) {
            $("#allowcountry").closest("tr").show();
          }
    }
    function hideBlockedCountriesSection() {
        if ($("#ip_check_support").is(":checked")) {
            $("#blockcountry").closest("tr").hide();
        } else if($("#ip_check_support").is(":not(:checked)")) {
            $("#blockcountry").closest("tr").show();
          }
    }

    $("#spam-countries").click(function () {
       
        const spamCountries = ["ru", "cn"];
        let blockedCountriesSelect = document.querySelector('#blockcountry select').tomselect;
        blockedCountriesSelect.setValue(spamCountries);
    })

});

// Update the table data when a row edited
let savedTableBodies = document.querySelectorAll("#savedFormData tbody");
if (savedTableBodies.length > 0) {
    savedTableBodies.forEach(function(tableBody) {
        tableBody.addEventListener("input", function (event) {
            let formContainer = this.closest("div");
            if (formContainer) {
                updateHiddenInputValue(formContainer);
            }
        }, false);
    });
}



function addData(button) {
    let formContainer = button.closest("div");

    var formIdInput = formContainer.querySelector('#formIdInput');
    var fieldIdInput = formContainer.querySelector('#fieldIdInput');
    var savedTableBody = formContainer.querySelector('#savedFormData tbody');

    var formId = formIdInput.value.trim();
    var fieldId = fieldIdInput.value.trim();

    if (formId !== '' && fieldId !== '') {
        var newRow = savedTableBody.insertRow(-1);
        var formIdCell = newRow.insertCell(0);
        var fieldIdCell = newRow.insertCell(1);
        var actionCell = newRow.insertCell(2); // Add cell for the "Delete" button

        formIdCell.textContent = formId;
        fieldIdCell.textContent = fieldId;
        actionCell.innerHTML = '<button type="button" onclick="deleteRow(this)">Delete</button>'; // Add "Delete" button

        // Clear the input fields after adding the pair
        formIdInput.value = '';
        fieldIdInput.value = '';

        // Update the hidden input value with the updated table data
        updateHiddenInputValue(formContainer);
    }
}

function deleteRow(btn) {
    let closestDivParent = btn.closest("div");
    let row = btn.parentNode.parentNode;
    row.parentNode.removeChild(row);
    updateHiddenInputValue(closestDivParent);
  }
  


function updateHiddenInputValue(formContainer) {
    var tableRows = formContainer.querySelector('#savedFormData').rows;
    var formData = [];

    for (var i = 1; i < tableRows.length; i++) { // Start from index 1 to skip the header row
        var formId = tableRows[i].cells[0].textContent.trim();
        var fieldId = tableRows[i].cells[1].textContent.trim();
        formData.push({ formId: formId, fieldId: fieldId });
    }

    var hiddenInput = formContainer.querySelector('#formDataInput');
    hiddenInput.value = JSON.stringify(formData);
}